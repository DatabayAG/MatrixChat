<?php

declare(strict_types=1);

/**
* This file is part of ILIAS, a powerful learning management system
* published by ILIAS open source e-Learning e.V.
*
* ILIAS is licensed with the GPL-3.0,
* see https://www.gnu.org/licenses/gpl-3.0.en.html
* You should have received a copy of said license along with the
* source code, too.
*
* If this is not the case or you just want to try ILIAS, you'll find
* us at:
* https://www.ilias.de
* https://github.com/ILIAS-eLearning
*
*********************************************************************/

namespace ILIAS\Plugin\MatrixChatClient\Libs\JsonTranslationLoader;

use RecursiveIteratorIterator;
use RecursiveArrayIterator;
use Exception;
use Throwable;

/**
 * Class JsonTranslationLoader
 * @package ILIAS\Plugin\MatrixChatClient\Libs\JsonTranslationLoader
 */
class JsonTranslationLoader
{
    /**
     * @var string
     */
    private $baseDir;

    public function __construct(string $baseDir = "lang")
    {
        $this->baseDir = $baseDir;
    }

    /**
     * @throws Exception
     */
    public function load(?array $langKeys = null) : void
    {
        if (!@is_dir($this->baseDir)) {
            throw new Exception("Directory $this->baseDir does not exist");
        }
        if (!@is_readable($this->baseDir)) {
            throw new Exception("Directory $this->baseDir is not readable");
        }
        if (!@is_writable($this->baseDir)) {
            throw new Exception("Directory $this->baseDir is not writable");
        }

        /**
         * @var array<string, string> $languageFiles
         */
        $languageFiles = array_filter(
            $this->findJsonFiles($this->baseDir),
            static function (string $key) use ($langKeys) : bool {
                return in_array($key, $langKeys, true);
            },
            ARRAY_FILTER_USE_KEY
        );

        foreach ($languageFiles as $langKey => $filePath) {
            $fileContent = file_get_contents($filePath);

            try {
                $json = json_decode($fileContent, true);
            } catch (Throwable $e) {
                continue;
            }

            //ILIAS DB-Translation
            $iterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($json));
            $iliasTranslationFileHandle = fopen($this->baseDir . "/" . "ilias_$langKey.lang", 'wb');
            fwrite(
                $iliasTranslationFileHandle,
                "/* This file was automatically generated from the $langKey.lang.json file. DO NOT EDIT! */\n\n"
            );
            foreach ($iterator as $value) {
                if (!$value) {
                    continue;
                }
                $path = [];
                foreach (range(0, $iterator->getDepth()) as $depth) {
                    $path[] = $iterator->getSubIterator($depth)->key();
                }

                $identifier = implode(".", $path);
                fwrite($iliasTranslationFileHandle, "$identifier#:#$value" . PHP_EOL);
            }
            fclose($iliasTranslationFileHandle);
        }
    }

    private function findJsonFiles(string $startingDir) : array
    {
        $jsonFiles = [];

        if (!@is_dir($startingDir) || !@is_readable($startingDir)) {
            return $jsonFiles;
        }

        $dir = opendir($startingDir);

        while ($file = readdir($dir)) {
            if ($file === "." || $file === "..") {
                continue;
            }

            if (@is_file("$startingDir/$file")) {
                $langMatch = null;
                if (preg_match('/^(.*)\.lang\.json$/m', $file, $langMatch) === 1) {
                    $jsonFiles[$langMatch[1]] = "$startingDir/$langMatch[0]";
                }
            } elseif (@is_dir("$startingDir/$file")) {
                $jsonFiles = array_merge($jsonFiles, $this->findJsonFiles("$startingDir/$file"));
            }
        }
        closedir($dir);

        return $jsonFiles;
    }
}
