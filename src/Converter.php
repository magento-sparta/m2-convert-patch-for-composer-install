<?php

namespace Converter;

class Converter
{
    const MODULE = 'Module';
    const INVENTORY = 'Inventory';
    const RECAPTCHA = 'ReCaptcha';
    const TWOFACTOR = 'TwoFactor';
    const SECURITYTXT = 'Securitytxt';
    const BRAINTREE_CORE = 'Braintree/';
    const BRAINTREE = 'Braintree';
    const LIVESEARCH = 'LiveSearch';
    const ADMINHTML_DESIGN = 'AdminhtmlDesign';
    const FRONTEND_DESIGN = 'FrontendDesign';
    const LIBRARY_AMQP = 'LibraryAmqp';
    const LIBRARY_BULK = 'LibraryBulk';
    const LIBRARY_FOREIGN_KEY = 'LibraryForeignKey';
    const LIBRARY_MESSAGE_QUEUE = 'LibraryMessageQueue';
    const LIBRARY = 'Library';

    protected $filename;
    protected $reverse;

    protected $nonComposerPath = [
        self::MODULE => 'app/code/Magento/',
        self::INVENTORY => 'Inventory',
        self::RECAPTCHA => 'ReCaptcha',
        self::TWOFACTOR => 'TwoFactor',
        self::SECURITYTXT => 'Securitytxt',
        self::BRAINTREE_CORE => 'Braintree/',
        self::BRAINTREE => 'Braintree',
        self::LIVESEARCH => 'LiveSearch',
        self::ADMINHTML_DESIGN => 'app/design/adminhtml/Magento/',
        self::FRONTEND_DESIGN => 'app/design/frontend/Magento/',
        self::LIBRARY_AMQP => 'lib/internal/Magento/Framework/Amqp/',
        self::LIBRARY_BULK => 'lib/internal/Magento/Framework/Bulk/',
        self::LIBRARY_FOREIGN_KEY => 'lib/internal/Magento/Framework/ForeignKey/',
        self::LIBRARY_MESSAGE_QUEUE => 'lib/internal/Magento/Framework/MessageQueue/',
        self::LIBRARY => 'lib/internal/Magento/Framework/'
    ];
    protected $composerPath = [
        self::MODULE => 'vendor/magento/module-',
        self::INVENTORY => 'vendor/magento/module-inventory-',
        self::RECAPTCHA => 'vendor/magento/module-re-captcha-',
        self::TWOFACTOR => 'vendor/magento/module-two-factor-',
        self::SECURITYTXT => 'vendor/magento/module-securitytxt-',
        self::BRAINTREE_CORE => 'vendor/paypal/module-braintree-core/',
        self::BRAINTREE => 'vendor/paypal/module-braintree-',
        self::LIVESEARCH => 'vendor/magento/module-live-search-',
        self::ADMINHTML_DESIGN => 'vendor/magento/theme-adminhtml-',
        self::FRONTEND_DESIGN => 'vendor/magento/theme-frontend-',
        self::LIBRARY_AMQP => 'vendor/magento/framework-amqp/',
        self::LIBRARY_BULK => 'vendor/magento/framework-bulk/',
        self::LIBRARY_FOREIGN_KEY => 'vendor/magento/framework-foreign-key/',
        self::LIBRARY_MESSAGE_QUEUE => 'vendor/magento/framework-message-queue/',
        self::LIBRARY => 'vendor/magento/framework/'
    ];

    protected $skipConversion = [
        self::MODULE => ['app/code/Magento/SupportDebugger']
    ];

    public function __construct(array $params = [])
    {
        $this->filename = $params[0] ?? null;
        $this->reverse = $params[1] ?? false;
    }

    protected function validateFile()
    {
        if (!$this->filename) {
            printf("Error! Please provide a file to convert.\n");
            fputs(STDERR, "\033[31m --- Error! Please provide a file to convert. --- \n");
            exit(1);
        }
        if (!file_exists($this->filename) || is_dir($this->filename)) {
            printf("Error! File %s does not exist.\n", $this->filename);
            fputs(STDERR, "\033[31m --- Error! File $this->filename does not exist. --- \n");
            exit(1);
        }

        if (!is_readable($this->filename)) {
            printf("Error! Can not read file %s.\n", $this->filename);
            fputs(STDERR, "\033[31m --- Error! Can not read file $this->filename. --- \n");
            exit(2);
        }
    }

    protected function checkForRenaming()
    {
        $content = file_get_contents($this->filename);
        if (strpos($content, 'rename from') && strpos($content, 'rename to')) {
            printf("Warning! File contains renaming. Please try to recreate it using the following command:\ngit diff -M90%% commit1 commit2 > test.patch \n");
            fputs(STDERR, "\033[31m Warning! File $this->filename contains renaming - please try to recreate it using the following command:\n\n git diff -M90% commit1 commit2 > test.patch\n");
            exit(3);
        }
    }

    public function camelCaseToDashedString($value)
    {
        return trim(preg_replace_callback('/((?:^|[A-Z])[a-z]+)|([\d])/', [$this, 'splitCamelCaseByDashes'], $value), '-');
    }

    public function splitCamelCaseByDashes($value)
    {
        if (!empty($value[2])) {
            return '-' . strtolower($value[1]) . $value[2];
        }

        return '-' . strtolower($value[0]);
    }

    public function shouldSkipConversion($type, $matches)
    {
        if (array_key_exists($type, $this->skipConversion)) {
            foreach ($this->skipConversion[$type] as $path) {
                $escapedPath = addcslashes($path, '/');
                if (preg_match_all('/' . $escapedPath . '/', $matches[0]))
                    return true;
            }
        }

        return false;
    }

    public function g2c(&$content)
    {
        foreach ($this->nonComposerPath as $type => $path) {
            $escapedPath = addcslashes($path, '/');
            $needProcess = in_array($type, [self::MODULE, self::INVENTORY, self::RECAPTCHA, self::TWOFACTOR, self::SECURITYTXT, self::BRAINTREE, self::ADMINHTML_DESIGN, self::FRONTEND_DESIGN]);

            /**
             * Example:
             * (     1     )                 (    2    )(        3          )                 (    4    )(       5        )
             * diff --git a/app/code/Magento/SomeModule/Some/Path/File.ext b/app/code/Magento/SomeModule/Some/Path/File.ext
             *
             * (     1     )                                          ()(     3     )                                          ()(    5   )
             * diff --git a/lib/internal/Magento/Framework/MessageQueue/Config.php b/lib/internal/Magento/Framework/MessageQueue/Config.php
             */
            $content = preg_replace_callback(
                '~(^diff --git\s+(?:a\/)?)' . $escapedPath . '([-\w]+\/)?([^\s]+\s+(?:b\/)?)' . $escapedPath . '([-\w]+\/)?([^\s]+)$~m',
                function ($matches) use ($type, $needProcess) {
                    if ($this->shouldSkipConversion($type, $matches)) {
                        return $matches[0];
                    }
                    return $matches[1] . ($matches[2] ? $this->composerPath[$type] : rtrim($this->composerPath[$type], "-"))
                        . ($needProcess ? $this->camelCaseToDashedString($matches[2]) : $matches[2])
                        . $matches[3] . ($matches[2] ? $this->composerPath[$type] : rtrim($this->composerPath[$type], "-"))
                        . ($needProcess ? $this->camelCaseToDashedString($matches[4]) : $matches[4])
                        . $matches[5];
                },
                $content
            );

            // (  1 )                 (    2   )
            // +++ b/app/code/Magento/SomeModule...
            $content = preg_replace_callback(
                '~(^(?:---|\+\+\+|Index:)\s+(?:a\/|b\/)?)' . $escapedPath . '([-\w]+)~m',
                function ($matches) use ($type, $needProcess) {
                    if ($this->shouldSkipConversion($type, $matches)) {
                        return $matches[0];
                    }
                    return $matches[1] . $this->composerPath[$type]
                        . ($needProcess ? $this->camelCaseToDashedString($matches[2]) : $matches[2]);
                },
                $content
            );

            // (  1 )(    2   )
            // +++ b/SomeModule
            $content = preg_replace_callback(
                '~(^(?:---|\+\+\+|Index:)\s+(?:a\/|b\/)?)' . $escapedPath . '~m',
                function ($matches) use ($type, $needProcess) {
                    if ($this->shouldSkipConversion($type, $matches)) {
                        return $matches[0];
                    }
                    return $matches[1] . (rtrim($this->composerPath[$type], "-"));
                },
                $content
            );

            // (     1     )                (    2   )
            // rename from app/code/Magento/SomeModule...
            $content = preg_replace_callback(
                '~(^rename\s+(?:from|to)\s+)' . $escapedPath . '([-\w]+)~m',
                function ($matches) use ($type, $needProcess) {
                    if ($this->shouldSkipConversion($type, $matches)) {
                        return $matches[0];
                    }
                    return $matches[1] . $this->composerPath[$type]
                        . ($needProcess ? $this->camelCaseToDashedString($matches[2]) : $matches[2]);
                },
                $content
            );
        }
    }

    public function dashedStringToCamelCase($string)
    {
        return str_replace('-', '', ucwords($string, '-'));
    }

    public function c2g(&$content)
    {
        foreach ($this->composerPath as $type => $path) {
            $escapedPath = addcslashes($path, '/');
            $needProcess = $type != self::FRONTEND_DESIGN && $type != self::ADMINHTML_DESIGN;

            /**
             * Example:
             * (     1     )               (        2        )(         3         )               (        4        )(       5        )
             * diff --git a/vendor/magento/module-some-module/Some/Path/File.ext b/vendor/magento/module-some-module/Some/Path/File.ext
             *
             * (     1     )                                     ()(     3     )                                     ()(    5   )
             * diff --git a/vendor/magento/framework-message-queue/Config.php b/vendor/magento/framework-message-queue/Config.php
             */
            $content = preg_replace_callback(
                '~(^diff --git\s+(?:a\/)?)' . $escapedPath . '([-\w]+\/)?([^\s]+\s+(?:b\/)?)' . $escapedPath . '([-\w]+\/)?([^\s]+)$~m',
                function ($matches) use ($type, $needProcess) {
                    return $matches[1] . $this->nonComposerPath[$type]
                        . ($needProcess ? $this->dashedStringToCamelCase($matches[2]) : $matches[2])
                        . $matches[3] . $this->nonComposerPath[$type]
                        . ($needProcess ? $this->dashedStringToCamelCase($matches[4]) : $matches[4])
                        . $matches[5];
                },
                $content
            );

            // (  1 )               (        2       )
            // +++ b/vendor/magento/module-some-module...
            $content = preg_replace_callback(
                '~(^(?:---|\+\+\+|Index:)\s+(?:a\/|b\/)?)' . $escapedPath . '([-\w]+)~m',
                function ($matches) use ($type, $needProcess) {
                    return $matches[1] . $this->nonComposerPath[$type]
                        . ($needProcess ? $this->dashedStringToCamelCase($matches[2]) : $matches[2]);
                },
                $content
            );

            // (     1     )                (        2       )
            // rename from vendor/magento/module-some-module...
            $content = preg_replace_callback(
                '~(^rename\s+(?:from|to)\s+)' . $escapedPath . '([-\w]+)~m',
                function ($matches) use ($type, $needProcess) {
                    return $matches[1] . $this->nonComposerPath[$type]
                        . ($needProcess ? $this->dashedStringToCamelCase($matches[2]) : $matches[2]);
                },
                $content
            );
        }
    }

    public function convert()
    {
        $this->validateFile();
        $this->checkForRenaming();
        $content = file_get_contents($this->filename);

        if ($this->reverse) {
            $this->c2g($content);
        } else {
            $this->g2c($content);
        }

        return $content;
    }
}