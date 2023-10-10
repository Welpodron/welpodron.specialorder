<?

use Bitrix\Main\Loader;

CJSCore::RegisterExt('welpodron.specialorder', [
    'js' => '/bitrix/js/welpodron.specialorder/script.js',
    'skip_core' => true,
    'rel' => ['welpodron.core.templater'],
]);

CJSCore::RegisterExt('welpodron.forms.specialorder.add', [
    'js' => '/bitrix/js/welpodron.specialorder/forms/add/script.js',
    'skip_core' => true,
    'rel' => ['welpodron.specialorder', 'welpodron.core.templater'],
]);

Loader::registerAutoLoadClasses(
    'welpodron.specialorder',
    [
        'Welpodron\SpecialOrder\Utils' => 'lib/utils/utils.php',
    ]
);
