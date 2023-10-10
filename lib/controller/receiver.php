<?

namespace Welpodron\SpecialOrder\Controller;

use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Error;
use Bitrix\Main\Loader;
use Bitrix\Main\Context;
use Bitrix\Sale\Basket;
use Bitrix\Catalog\Product\Basket as _Basket;
use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Json;
use Bitrix\Main\UserConsent\Consent;
use Bitrix\Sale\Order as _Order;
use Bitrix\Main\UserConsent\Agreement;
use Welpodron\SpecialOrder\Utils as OrderUtils;
use Bitrix\Catalog\ProductTable;
use Bitrix\Iblock\ElementPropertyTable;
use Bitrix\Iblock\PropertyTable;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\Type\DateTime;

// welpodron:order.Receiver.add

class Receiver extends Controller
{
    const DEFAULT_ORDER_MODULE_ID = 'welpodron.specialorder';
    const DEFAULT_FIELD_VALIDATION_ERROR_CODE = "FIELD_VALIDATION_ERROR";
    const DEFAULT_FORM_GENERAL_ERROR_CODE = "FORM_GENERAL_ERROR";
    const DEFAULT_GOOGLE_URL = "https://www.google.com/recaptcha/api/siteverify";

    const DEFAULT_ERROR_CONTENT = "При обработке Вашего запроса произошла ошибка, повторите попытку позже или свяжитесь с администрацией сайта";

    public function configureActions()
    {
        return [
            'add' => [
                'prefilters' => []
            ]
        ];
    }

    private function loadModules()
    {
        if (!Loader::includeModule(self::DEFAULT_ORDER_MODULE_ID)) {
            throw new \Exception('Модуль ' . self::DEFAULT_ORDER_MODULE_ID . ' не удалось подключить');
        }

        if (!Loader::includeModule('iblock')) {
            throw new \Exception('Модуль iblock не удалось подключить');
        }

        if (!Loader::includeModule("catalog")) {
            throw new \Exception('Модуль catalog не удалось подключить');
        }

        if (!Loader::includeModule("sale")) {
            throw new \Exception('Модуль sale не удалось подключить');
        }
    }

    private function validateField($arField, $value, $bannedSymbols = [])
    {
        // Проверка на обязательность заполнения
        if ($arField['IS_REQUIRED'] == 'Y' && !strlen($value)) {
            $error = 'Поле: "' . $arField['NAME'] . '" является обязательным для заполнения';
            $this->addError(new Error($error, self::DEFAULT_FIELD_VALIDATION_ERROR_CODE, $arField['CODE']));
            return [
                'FIELD_CODE' => $arField['CODE'],
                'FIELD_VALUE' => $value,
                'FIELD_VALID' => false,
                'FIELD_ERROR' => $error,
            ];
        }

        // Проверка на наличие запрещенных символов 
        if (strlen($value)) {
            if ($bannedSymbols) {
                foreach ($bannedSymbols as $bannedSymbol) {
                    if (strpos($value, $bannedSymbol) !== false) {
                        $error = 'Поле: "' . $arField['NAME'] . '" содержит один из запрещенных символов: "' . implode(' ', $bannedSymbols) . '"';
                        $this->addError(new Error($error, self::DEFAULT_FIELD_VALIDATION_ERROR_CODE, $arField['CODE']));
                        return [
                            'FIELD_CODE' => $arField['CODE'],
                            'FIELD_VALUE' => $value,
                            'FIELD_VALID' => false,
                            'FIELD_ERROR' => $error,
                        ];
                    }
                }
            }
        }

        $currentDate = new DateTime(null, 'Y-m-d', new \DateTimeZone('Europe/Moscow'));
        $currentDate->setTime(0, 0);

        //! Особая проверка для полей начала и конца аренды 
        if ($arField['CODE'] == 'DATE_START') {
            try {
                $startDate = new DateTime($value, 'Y-m-d', new \DateTimeZone('Europe/Moscow'));
                $startDate->setTime(0, 0);
            } catch (\Throwable $th) {
                $error = 'Поле: "' . $arField['NAME'] . '" не соответствует формату: "год-месяц-день"';
                $this->addError(new Error($error, self::DEFAULT_FIELD_VALIDATION_ERROR_CODE, $arField['CODE']));
                return [
                    'FIELD_CODE' => $arField['CODE'],
                    'FIELD_VALUE' => $value,
                    'FIELD_VALID' => false,
                    'FIELD_ERROR' => $error,
                ];
            }


            if ($startDate < $currentDate) {
                $error = 'Начало временного периода не может быть меньше текущей даты';
                $this->addError(new Error($error, self::DEFAULT_FIELD_VALIDATION_ERROR_CODE, $arField['CODE']));
                return [
                    'FIELD_CODE' => $arField['CODE'],
                    'FIELD_VALUE' => $value,
                    'FIELD_VALID' => false,
                    'FIELD_ERROR' => $error,
                ];
            }
        }

        if ($arField['CODE'] == 'DATE_END') {
            try {
                $endDate = new DateTime($value, 'Y-m-d', new \DateTimeZone('Europe/Moscow'));
                $endDate->setTime(0, 0);
            } catch (\Throwable $th) {
                $error = 'Поле: "' . $arField['NAME'] . '" не соответствует формату: "год-месяц-день"';
                $this->addError(new Error($error, self::DEFAULT_FIELD_VALIDATION_ERROR_CODE, $arField['CODE']));
                return [
                    'FIELD_CODE' => $arField['CODE'],
                    'FIELD_VALUE' => $value,
                    'FIELD_VALID' => false,
                    'FIELD_ERROR' => $error,
                ];
            }

            if ($endDate < $currentDate) {
                $error = 'Конец временного периода не может быть меньше текущей даты';
                $this->addError(new Error($error, self::DEFAULT_FIELD_VALIDATION_ERROR_CODE, $arField['CODE']));
                return [
                    'FIELD_CODE' => $arField['CODE'],
                    'FIELD_VALUE' => $value,
                    'FIELD_VALID' => false,
                    'FIELD_ERROR' => $error,
                ];
            }
        }

        return [
            'FIELD_CODE' => $arField['CODE'],
            'FIELD_VALUE' => $value,
            'FIELD_VALID' => true,
            'FIELD_ERROR' => '',
        ];
    }

    private function validateCaptcha($token)
    {
        if (!$token) {
            throw new \Exception('Ожидался токен от капчи. Запрос должен иметь заполненное POST поле: "g-recaptcha-response"');
        }

        $secretCaptchaKey = Option::get(self::DEFAULT_ORDER_MODULE_ID, 'GOOGLE_CAPTCHA_SECRET_KEY');

        $httpClient = new HttpClient();
        $googleCaptchaResponse = Json::decode($httpClient->post(self::DEFAULT_GOOGLE_URL, ['secret' => $secretCaptchaKey, 'response' => $token], true));

        if (!$googleCaptchaResponse['success']) {
            throw new \Exception('Произошла ошибка при попытке обработать ответ от сервера капчи, проверьте задан ли параметр "GOOGLE_CAPTCHA_SECRET_KEY" в настройках модуля');
        }
    }

    private function validateAgreement($arDataRaw)
    {
        $agreementProp = Option::get(self::DEFAULT_ORDER_MODULE_ID, 'AGREEMENT_PROPERTY');

        $agreementId = intval($arDataRaw[$agreementProp]);

        if ($agreementId <= 0) {
            $error = 'Поле является обязательным для заполнения';
            $this->addError(new Error($error, self::DEFAULT_FIELD_VALIDATION_ERROR_CODE, $agreementProp));
            return;
        }

        $agreement = new Agreement($agreementId);

        if (!$agreement->isExist() || !$agreement->isActive()) {
            throw new \Exception('Соглашение c id ' . $agreementId . ' не найдено или не активно');
        }

        return true;
    }

    public function addAction()
    {
        global $APPLICATION;

        try {
            $this->loadModules();

            if (!_Basket::isNotCrawler()) {
                throw new \Exception('Поисковые роботы не могут оформлять заказ');
            }

            if (!$_SERVER['HTTP_USER_AGENT']) {
                throw new \Exception('Поисковые роботы не могут оформлять заказ');
            } elseif (preg_match('/bot|crawl|curl|dataprovider|search|get|spider|find|java|majesticsEO|google|yahoo|teoma|contaxe|yandex|libwww-perl|facebookexternalhit/i', $_SERVER['HTTP_USER_AGENT'])) {
                throw new \Exception('Поисковые роботы не могут оформлять заказ');
            }

            $request = $this->getRequest();
            $arDataRaw = $request->getPostList()->toArray();

            if ($arDataRaw['sessid'] !== bitrix_sessid()) {
                throw new \Exception('Неверный идентификатор сессии');
            }

            // Проверка капчи если она включена
            $useCaptcha = Option::get(self::DEFAULT_ORDER_MODULE_ID, 'USE_CAPTCHA') == "Y";

            if ($useCaptcha) {
                $this->validateCaptcha($arDataRaw['g-recaptcha-response']);
            }

            $useCheckAgreement = Option::get(self::DEFAULT_ORDER_MODULE_ID, 'USE_AGREEMENT_CHECK') == "Y";

            if ($useCheckAgreement) {
                if (!$this->validateAgreement($arDataRaw)) {
                    return;
                }
            }

            $bannedSymbols = [];
            $bannedSymbolsRaw = preg_split('/(\s*,*\s*)*,+(\s*,*\s*)*/', trim(strval(Option::get(self::DEFAULT_ORDER_MODULE_ID, 'BANNED_SYMBOLS'))));
            if ($bannedSymbolsRaw) {
                $bannedSymbolsRawFiltered = array_filter($bannedSymbolsRaw, function ($value) {
                    return $value !== null && $value !== '';
                });
                $bannedSymbols = array_values($bannedSymbolsRawFiltered);
            }

            $arDataValid = [];

            $arFields = OrderUtils::getOrderFields(1);

            $arFields[] = [
                'NAME' => 'Комментарий',
                'CODE'  => 'USER_DESCRIPTION',
                'IS_REQUIRED' => 'N',
            ];

            $arFields[] = [
                'NAME' => 'Начало временного периода',
                'CODE' => 'DATE_START',
                'IS_REQUIRED' => 'Y',
            ];

            $arFields[] = [
                'NAME' => 'Конец временного периода',
                'CODE' => 'DATE_END',
                'IS_REQUIRED' => 'Y',
            ];

            $userName = '';
            $userEmail = '';
            $userPhone = '';

            foreach ($arFields as $arField) {
                $fieldCode = $arField['CODE'];
                $fieldValue = $arDataRaw[$fieldCode];

                $arResult = $this->validateField($arField, $fieldValue, $bannedSymbols);

                if ($arResult['FIELD_VALID']) {
                    $arDataValid[$fieldCode] = $fieldValue;

                    if ($arField['IS_PROFILE_NAME'] == "Y" && $arField['IS_PAYER'] == "Y") {
                        $userName = $fieldValue;
                    }

                    if ($arField['IS_EMAIL'] == "Y") {
                        $userEmail = $fieldValue;
                    }

                    if ($arField['IS_PHONE'] == "Y") {
                        $userPhone = $fieldValue;
                    }
                } else {
                    return;
                }
            }

            $startDate = new DateTime($arDataValid['DATE_START'], 'Y-m-d', new \DateTimeZone('Europe/Moscow'));
            $startDate->setTime(0, 0);

            $endDate = new DateTime($arDataValid['DATE_END'], 'Y-m-d', new \DateTimeZone('Europe/Moscow'));
            $endDate->setTime(0, 0);

            $totalDays = $startDate->getDiff($endDate)->days;

            if ($totalDays === false) {
                $error = 'Не удалось посчитать разницу в днях между начальной и конечной датой';
                $this->addError(new Error($error, self::DEFAULT_FORM_GENERAL_ERROR_CODE));
                return;
            }

            $siteId = Context::getCurrent()->getSite();

            $basket = Basket::create($siteId);

            $arRequestProducts = $arDataRaw['products'];

            if (!$arRequestProducts || !is_array($arRequestProducts)) {
                $arRequestProducts = [];
            }

            $arAddedProducts = [];

            //! Данные для добавления в корзину представлены в виде: [p_id_1 => quantity_1, p_id_2 => quantity_2, ...] 
            foreach ($arRequestProducts as $productId => $productQuantity) {
                if (intval($productId) > 0 && intval($productQuantity) > 0) {
                    //! TODO: Добавить поддержку ограничения количества сверху 
                    $arAddedProducts[$productId] = $productQuantity;
                }
            }

            if (!$arAddedProducts) {
                $error = 'Не выбраны позиции для оформления заказа';
                $this->addError(new Error($error, self::DEFAULT_FORM_GENERAL_ERROR_CODE));
                return;
            }

            $arProducts = [];

            //! TODO: Добавить в настройки модуля свойство для выбора цены начиная со 2 дня 

            /*
            Цена за позицию в корзине считается по принципу:
    
            (БАЗОВАЯ_ЗА_1_ДЕНЬ + РАЗНИЦА_В_ДНЯХ * НАЧИНАЯ_СО_2_ДНЯ) * КОЛИЧЕСТВО_ТОВАРА_В_КОРЗИНЕ
    
            где: 
            
            БАЗОВАЯ_ЗА_1_ДЕНЬ - берется базовая цена из битрикса
            РАЗНИЦА_В_ДНЯХ - разница в днях между начальной и конечной датой
            НАЧИНАЯ_СО_2_ДНЯ - значение из настройки модуля свойство для выбора цены начиная со 2 дня 
            КОЛИЧЕСТВО_ТОВАРА_В_КОРЗИНЕ - текущее количество товара в корзине
            */

            $query = ProductTable::query();
            $query->setSelect(['ID']);
            $query->where('AVAILABLE', 'Y');
            $query->whereIn('ID', array_keys($arAddedProducts));
            $queryResult = $query->exec();

            while ($arProduct = $queryResult->fetch()) {
                $firstDayPrice = \CCatalogProduct::GetOptimalPrice($arProduct['ID']);

                $quantity = $arAddedProducts[$arProduct['ID']];

                if (!$firstDayPrice || !$quantity) {
                    continue;
                }

                $firstDayPrice = $firstDayPrice['RESULT_PRICE']['DISCOUNT_PRICE'];

                $propsQuery = ElementPropertyTable::query();
                $propsQuery->setSelect(['VALUE']);
                $propsQuery->registerRuntimeField(new Reference(
                    'p_t',
                    PropertyTable::class,
                    Join::on('this.IBLOCK_PROPERTY_ID', 'ref.ID')
                ));
                $propsQuery->where('IBLOCK_ELEMENT_ID', $arProduct['ID']);
                $propsQuery->where('p_t.CODE', 'SECOND_DAY_PRICE');

                $secondDayPrice = $propsQuery->exec()->fetch();

                if (!$secondDayPrice || !$secondDayPrice['VALUE']) {
                    $secondDayPrice = $firstDayPrice;
                } else {
                    $secondDayPrice = $secondDayPrice['VALUE'];
                }

                $productPrice = ($firstDayPrice + $totalDays * $secondDayPrice);

                $arProducts[] = [
                    'PRODUCT_ID' => $arProduct['ID'],
                    'PRODUCT_PROVIDER_CLASS' => '\Bitrix\Catalog\Product\CatalogProvider',
                    'IGNORE_CALLBACK_FUNC' => 'Y',
                    'CUSTOM_PRICE' => 'Y',
                    'PRICE' => $productPrice,
                    'QUANTITY' => $quantity
                ];
            }

            if (!$arProducts) {
                $error = 'Текущие позиции на данный момент недоступны для заказа';
                $this->addError(new Error($error, self::DEFAULT_FORM_GENERAL_ERROR_CODE));
                return;
            }

            foreach ($arProducts as $arProduct) {
                $item = $basket->createItem("catalog", $arProduct["PRODUCT_ID"]);
                unset($arProduct["PRODUCT_ID"]);
                $item->setFields($arProduct);
            }

            $userId = OrderUtils::getUserId($userEmail, $userPhone, $userName);

            $order = _Order::create($siteId, $userId);

            $order->isStartField();

            //TODO Добавить значение из модуля
            $order->setPersonTypeId(1);

            $order->setBasket($basket);

            if ($arDataValid['USER_DESCRIPTION']) {
                $order->setField(
                    'USER_DESCRIPTION',
                    $arDataValid['USER_DESCRIPTION']
                );
            }

            unset($arDataValid['USER_DESCRIPTION']);

            $propertyCollection = $order->getPropertyCollection();

            foreach ($propertyCollection as $propertyObj) {
                $propertyCode = $propertyObj->getField('CODE');

                if (isset($arDataValid[$propertyCode])) {
                    $propertyObj->setValue($arDataValid[$propertyCode]);
                }
            }

            $order->doFinalAction(true);
            $result = $order->save();

            if (!$result->isSuccess()) {
                throw new \Exception(implode(', ', $result->getErrorMessages()));
            }

            //TODO: ОЧИСТИТЬ КОРЗИНУ ПОЛЬЗОВАТЕЛЯ ПОСЛЕ ОФОРМЛЕНИЯ ЗАКАЗА И ПЕРЕЙТИ НА НОВЫХ МЕХАНИЗМ КОРЗИНЫ 

            if ($useCheckAgreement) {
                $agreementId = null;

                $agreementProp = Option::get(self::DEFAULT_ORDER_MODULE_ID, 'AGREEMENT_PROPERTY');

                if (isset($arDataValid[$agreementProp])) {
                    $agreementId = intval($arDataValid[$agreementProp]);
                } else {
                    $agreementId = intval($arDataRaw[$agreementProp]);
                }

                if ($agreementId > 0) {
                    Consent::addByContext($agreementId, null, null, [
                        'URL' => Context::getCurrent()->getServer()->get('HTTP_REFERER'),
                        'USER_ID' => $userId,
                    ]);
                }
            }

            $useSuccessContent = Option::get(self::DEFAULT_ORDER_MODULE_ID, 'USE_SUCCESS_CONTENT');

            $templateIncludeResult = "";

            if ($useSuccessContent == 'Y') {
                $templateIncludeResult =  Option::get(self::DEFAULT_ORDER_MODULE_ID, 'SUCCESS_CONTENT_DEFAULT');

                $successFile = Option::get(self::DEFAULT_ORDER_MODULE_ID, 'SUCCESS_FILE');

                if ($successFile) {
                    ob_start();
                    $APPLICATION->IncludeFile($successFile, [
                        'arMutation' => [
                            'PATH' => $successFile,
                            'PARAMS' => [],
                        ]
                    ], ["SHOW_BORDER" => false, "MODE" => "php"]);
                    $templateIncludeResult = ob_get_contents();
                    ob_end_clean();
                }
            }

            return $templateIncludeResult;
        } catch (\Throwable $th) {
            if (CurrentUser::get()->isAdmin()) {
                $this->addError(new Error($th->getMessage(), $th->getCode(), $th->getTraceAsString()));
                return;
            }

            try {
                $useErrorContent = Option::get(self::DEFAULT_ORDER_MODULE_ID, 'USE_ERROR_CONTENT');

                if ($useErrorContent == 'Y') {
                    $errorFile = Option::get(self::DEFAULT_ORDER_MODULE_ID, 'ERROR_FILE');

                    if (!$errorFile) {
                        $this->addError(new Error(Option::get(self::DEFAULT_ORDER_MODULE_ID, 'ERROR_CONTENT_DEFAULT'), self::DEFAULT_FORM_GENERAL_ERROR_CODE));
                        return;
                    }

                    ob_start();
                    $APPLICATION->IncludeFile($errorFile, [
                        'arMutation' => [
                            'PATH' => $errorFile,
                            'PARAMS' => [],
                        ]
                    ], ["SHOW_BORDER" => false, "MODE" => "php"]);
                    $templateIncludeResult = ob_get_contents();
                    ob_end_clean();
                    $this->addError(new Error($templateIncludeResult));
                    return;
                }

                $this->addError(new Error(self::DEFAULT_ERROR_CONTENT, self::DEFAULT_FORM_GENERAL_ERROR_CODE));
                return;
            } catch (\Throwable $th) {
                if (CurrentUser::get()->isAdmin()) {
                    $this->addError(new Error($th->getMessage(), $th->getCode(), $th->getTraceAsString()));
                    return;
                } else {
                    $this->addError(new Error(self::DEFAULT_ERROR_CONTENT, self::DEFAULT_FORM_GENERAL_ERROR_CODE));
                    return;
                }
            }
        }
    }
}
