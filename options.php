<?
if (!defined('B_PROLOG_INCLUDED') || constant('B_PROLOG_INCLUDED') !== true) {
    die();
}

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Context;
use Welpodron\Order\Utils;

use Welpodron\Core\Helper;

$moduleId = 'welpodron.specialorder';

Loader::includeModule($moduleId);

$arPersonTypes['-1'] = 'Выберите тип плательщика';

foreach (Utils::getPersonTypes() as $arPersonType) {
    $arPersonTypes[$arPersonType['ID']] = '[' . $arPersonType['ID'] . '] ' . $arPersonType["NAME"];
}

$arTabs = [
    [
        'DIV' => 'edit1',
        'TAB' => 'Настройки заказа',
        'TITLE' => 'Настройки заказа',
        'GROUPS' => [
            [
                'TITLE' => 'Основные настройки',
                'OPTIONS' => [
                    [
                        'NAME' => 'PERSON_TYPE_DEFAULT',
                        'LABEL' => 'Тип плательщика по умолчанию',
                        'VALUE' => Option::get($moduleId, 'PERSON_TYPE_DEFAULT'),
                        'TYPE' => 'selectbox',
                        'OPTIONS' => $arPersonTypes,
                        'REQUIRED' => 'Y',
                    ],
                ],
            ],
            [
                'TITLE' => 'Настройки внешнего вида ответа',
                'OPTIONS' => [
                    [
                        'NAME' => 'USE_SUCCESS_CONTENT',
                        'LABEL' => 'Использовать успешное сообщение',
                        'VALUE' => Option::get($moduleId, 'USE_SUCCESS_CONTENT'),
                        'TYPE' => 'checkbox',
                    ],
                    [
                        'NAME' => 'SUCCESS_FILE',
                        'LABEL' => 'PHP файл-шаблон успешного ответа',
                        'VALUE' => Option::get($moduleId, 'SUCCESS_FILE'),
                        'TYPE' => 'file',
                        'DESCRIPTION' => 'Если PHP файл-шаблон успешного ответа не задан, то будет использоваться содержимое успешного ответа по умолчанию',
                        'RELATION'  => 'USE_SUCCESS_CONTENT',
                    ],
                    [
                        'NAME' => 'SUCCESS_CONTENT_DEFAULT',
                        'LABEL' => 'Содержимое успешного ответа по умолчанию',
                        'VALUE' => Option::get($moduleId, 'SUCCESS_CONTENT_DEFAULT'),
                        'TYPE' => 'editor',
                        'RELATION'  => 'USE_SUCCESS_CONTENT',
                    ],
                    [
                        'NAME' => 'USE_ERROR_CONTENT',
                        'LABEL' => 'Использовать сообщение об ошибке',
                        'VALUE' => Option::get($moduleId, 'USE_ERROR_CONTENT'),
                        'TYPE' => 'checkbox',
                    ],
                    [
                        'NAME' => 'ERROR_FILE',
                        'LABEL' => 'PHP файл-шаблон ответа с ошибкой',
                        'VALUE' => Option::get($moduleId, 'ERROR_FILE'),
                        'TYPE' => 'file',
                        'DESCRIPTION' => 'Если PHP файл-шаблон ответа с ошибкой не задан, то будет использоваться содержимое ответа с ошибкой по умолчанию',
                        'RELATION'  => 'USE_ERROR_CONTENT',
                    ],
                    [
                        'NAME' => 'ERROR_CONTENT_DEFAULT',
                        'LABEL' => 'Содержимое ответа с ошибкой по умолчанию',
                        'VALUE' => Option::get($moduleId, 'ERROR_CONTENT_DEFAULT'),
                        'TYPE' => 'editor',
                        'RELATION'  => 'USE_ERROR_CONTENT',
                    ],
                ],
            ]
        ],
    ],
    [
        'DIV' => 'edit2',
        'TAB' => 'Настройки формы заказа',
        'TITLE' => 'Настройки формы заказа',
        'GROUPS' => [
            [
                'TITLE' => 'Валидация данных',
                'OPTIONS' => [
                    [
                        'NAME' => 'BANNED_SYMBOLS',
                        'LABEL' => 'Список запрещенных символов/слов (через запятую)',
                        'VALUE' => Option::get($moduleId, 'BANNED_SYMBOLS'),
                        'TYPE' => 'textarea',
                    ],
                ],
            ],
            [
                'TITLE' => 'Согласие на обработку персональных данных',
                'OPTIONS' => [
                    [
                        'NAME' => 'USE_AGREEMENT_CHECK',
                        'LABEL' => 'Проверять в данных, пришедших с клиента, наличие согласия на обработку персональных данных',
                        'VALUE' => Option::get($moduleId, 'USE_AGREEMENT_CHECK'),
                        'TYPE' => 'checkbox',
                    ],
                    [
                        'NAME' => 'AGREEMENT_PROPERTY',
                        'LABEL' => 'Код поля в котором хранится согласие на обработку персональных данных',
                        'VALUE' => Option::get($moduleId, 'AGREEMENT_PROPERTY'),
                        'TYPE' => 'text',
                        'RELATION' => 'USE_AGREEMENT_CHECK',
                    ],
                ],
            ],
            [
                'TITLE' => 'Настройки Google reCAPTCHA v3',
                'OPTIONS' => [
                    [
                        'NAME' => 'USE_CAPTCHA',
                        'LABEL' => 'Использовать Google reCAPTCHA v3',
                        'VALUE' => Option::get($moduleId, 'USE_CAPTCHA'),
                        'TYPE' => 'checkbox',
                    ],
                    [
                        'NAME' => 'GOOGLE_CAPTCHA_SECRET_KEY',
                        'LABEL' => 'Секретный ключ',
                        'VALUE' => Option::get($moduleId, 'GOOGLE_CAPTCHA_SECRET_KEY'),
                        'TYPE' => 'text',
                        'RELATION' => 'USE_CAPTCHA',
                    ],
                    [
                        'NAME' => 'GOOGLE_CAPTCHA_PUBLIC_KEY',
                        'LABEL' => 'Публичный ключ (ключ сайта)',
                        'VALUE' => Option::get($moduleId, 'GOOGLE_CAPTCHA_PUBLIC_KEY'),
                        'TYPE' => 'text',
                        'RELATION' => 'USE_CAPTCHA',
                    ],
                ],
            ],
        ],
    ]
];

if (Loader::includeModule('welpodron.core')) {
    Helper::buildOptions($moduleId, $arTabs);
} else {
    echo 'Модуль welpodron.core не установлен';
}
