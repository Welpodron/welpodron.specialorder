<?

namespace Welpodron\SpecialOrder;

use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Security\Random;
use Bitrix\Main\UserTable;
use Bitrix\Main\UserPhoneAuthTable;
use Bitrix\Sale\Property;

class Utils
{
    static private function addUser($email = '', $phone = '', $name = '', $siteId = SITE_ID)
    {
        $userId = false;

        $email = $email ? trim((string)$email) : '';

        $login = $email;

        if (empty($email)) {
            if (!empty($phone)) {
                $login = $phone;
            }
        }

        if (empty($login)) {
            $login = Random::getString(5) . mt_rand(0, 99999);
        }

        $pos = mb_strpos($login, '@');
        if ($pos !== false) {
            $login = mb_substr($login, 0, $pos);
        }

        if (mb_strlen($login) > 47) {
            $login = mb_substr($login, 0, 47);
        }

        $login = str_pad($login, 3, '_');

        $dbUserLogin = \CUser::GetByLogin($login);

        if ($userLoginResult = $dbUserLogin->Fetch()) {
            do {
                $loginTmp = $login . mt_rand(0, 99999);
                $dbUserLogin = \CUser::GetByLogin($loginTmp);
            } while ($userLoginResult = $dbUserLogin->Fetch());

            $login = $loginTmp;
        }

        $groupIds = [];
        $defaultGroups = Option::get('main', 'new_user_registration_def_group', '');

        if (!empty($defaultGroups)) {
            $groupIds = explode(',', $defaultGroups);
        }

        $password = \CUser::GeneratePasswordByPolicy($groupIds);

        $fields = [
            'LOGIN' => $login,
            'NAME' => $name,
            'PASSWORD' => $password,
            'CONFIRM_PASSWORD' => $password,
            'EMAIL' => $email,
            'GROUP_ID' => $groupIds,
            'ACTIVE' => 'Y',
            'LID' => $siteId,
            'PERSONAL_PHONE' => $phone,
            // 'PHONE_NUMBER' => $phone,
        ];

        $user = new \CUser;
        $addResult = $user->Add($fields);

        if (intval($addResult) <= 0) {
            throw new \Exception((($user->LAST_ERROR <> '') ? ': ' . $user->LAST_ERROR : ''));
        } else {
            global $USER;

            $userId = intval($addResult);
            $USER->Authorize($addResult);

            if ($USER->IsAuthorized()) {
                \CUser::SendUserInfo($USER->GetID(), $siteId, 'Вы были успешно зарегистрированы. Для установки пароля к вашему аккаунту перейдите по ссылке ниже.', true);
            } else {
                throw new \Exception("Ошибка авторизации");
            }
        }

        return $userId;
    }

    static public function getUserId($email = '', $phone = '', $name = '')
    {
        global $USER;

        if ($USER->IsAuthorized()) {
            return $USER->GetID();
        }

        if (
            (Option::get('main', 'new_user_email_uniq_check', '') === 'Y' || Option::get('main', 'new_user_phone_auth', '') === 'Y') && ($email != '' || $phone != '')
        ) {
            if ($email != '') {
                $res = UserTable::getRow([
                    'filter' => [
                        '=ACTIVE' => 'Y',
                        '=EMAIL' => $email,
                        '!=EXTERNAL_AUTH_ID' => array_diff(UserTable::getExternalUserTypes(), ['shop'])
                    ],
                    'select' => ['ID'],
                ]);

                if (isset($res['ID'])) {
                    return intval($res['ID']);
                }
            }

            if ($phone != '') {
                $res = UserTable::getRow([
                    'filter' => [
                        'ACTIVE' => 'Y',
                        '!=EXTERNAL_AUTH_ID' => array_diff(UserTable::getExternalUserTypes(), ['shop']),
                        [
                            'LOGIC' => 'OR',
                            '=PHONE_AUTH.PHONE_NUMBER' => UserPhoneAuthTable::normalizePhoneNumber($phone) ?: '',
                            '=PERSONAL_PHONE' => $phone,
                            '=PERSONAL_MOBILE' => $phone,
                        ],
                    ],
                    'select' => ['ID'],
                ]);
                if (isset($res['ID'])) {
                    return intval($res['ID']);
                }
            }

            return self::addUser($email, $phone, $name);
        } elseif ($email != '' || Option::get('main', 'new_user_email_required', '') === 'N') {
            return self::addUser($email, $phone, $name);
        }

        return \CSaleUser::GetAnonymousUserID();
    }

    static public function getOrderFields($personType = 1)
    {
        if (intval($personType) <= 0) {
            throw new \Exception("Передан неверный тип плательщика");
        }

        if (!Loader::includeModule('sale')) {
            throw new \Exception("Не удалось подключить модуль sale");
        }

        $arProps = [];

        $dbProps = Property::getList([
            'select' => [
                'ID',
                'IS_REQUIRED' => 'REQUIRED',
                'NAME',
                'TYPE',
                'SETTINGS',
                'IS_EMAIL',
                'IS_PROFILE_NAME',
                'IS_PAYER',
                'CODE',
                'IS_PHONE',
            ],
            //! Игнорируем адреса и все поля которые содержат default_value   
            'filter' => [
                'PERSON_TYPE_ID' => $personType,
                'IS_ADDRESS' => "N",
                'ACTIVE' => 'Y',
                // 'REQUIRED' => 'Y',
                '!CODE' => false,
                'DEFAULT_VALUE' => false
            ],
        ]);

        while ($arProp = $dbProps->fetch()) {
            $arProps[] = $arProp;
        }

        return $arProps;
    }
}
