<?php

declare(strict_types=1);
/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *********************************************************************/

namespace ILIAS\Plugin\MatrixChatClient\Libs\IliasConfigLoader\Model;

use ReflectionClass;
use ilSetting;
use ILIAS\Plugin\MatrixChatClient\Libs\IliasConfigLoader\Exception\ConfigLoadException;
use ReflectionException;
use ILIAS\Plugin\MatrixChatClient\Libs\IliasConfigLoader\Exception\ConfigLoadInvalidTypeException;
use ILIAS\Plugin\MatrixChatClient\Libs\IliasConfigLoader\Exception\ConfigLoadNonPrimitiveDataTypeDetected;
use TypeError;

/**
 * Class ConfigBase
 *
 * @package ILIAS\Plugin\MatrixChatClient\Model
 * @author  Marvin Beym <mbeym@databay.de>
 */
class ConfigBase
{
    //Required in >= ILIAS 8 because default value is only allowed to be string or null. Handle default value using manually.
    private const DEFAULT_VALUE = "configBase_default_value";

    /**
     * @var string
     */
    private $settingsPrefix;
    /**
     * @var ilSetting
     */
    private $settings;

    public function __construct(ilSetting $settings, string $settingsPrefix = "config_")
    {
        $this->settingsPrefix = $settingsPrefix;
        $this->settings = $settings;
    }

    public function toArray($blacklist = []) : array
    {
        $data = [];
        foreach ($this->getProperties(new ReflectionClass($this))->getLoadable() as $loadableProperty) {
            $property = $loadableProperty->getProperty();
            $property->setAccessible(true);
            if (in_array($property->getName(), $blacklist, true)) {
                continue;
            }
            $data[$property->getName()] = $property->getValue($this);
        }
        return $data;
    }

    /**
     * @throws ConfigLoadException
     */
    public function save() : void
    {
        $properties = $this->getProperties(new ReflectionClass($this));
        foreach ($properties->getLoadable() as $loadable) {
            $property = $loadable->getProperty();
            $property->setAccessible(true);
            $value = $property->getValue($this);

            if (is_array($value)) {
                $value = serialize($value);
            }

            $this->settings->set($this->settingsPrefix . $property->getName(), $value);
        }

        if ($properties->hasUnloadable()) {
            throw new ConfigLoadException($properties->getUnloadable());
        }
    }

    /**
     * @return array<string, mixed>
     * @throws ReflectionException
     */
    private function getDefaultValues() : array
    {
        $defaultValues = [];
        $tmpEmptyObjectReflection = new ReflectionClass(new $this($this->settings, $this->settingsPrefix));
        $tmpEmptyObjectReflectionProps = $tmpEmptyObjectReflection->getDefaultProperties();

        foreach ($tmpEmptyObjectReflection->getProperties() as $property) {
            $property->setAccessible(true);
            $defaultValues[$property->getName()] = $tmpEmptyObjectReflectionProps[$property->getName()];
        }

        return $defaultValues;
    }

    /**
     * @throws ConfigLoadException
     */
    public function load() : self
    {
        $properties = $this->getProperties(new ReflectionClass($this));

        try {
            $defaultValues = $this->getDefaultValues();
        } catch (ReflectionException $e) {
            return $this;
        }

        foreach ($properties->getLoadable() as $loadableProperty) {
            $property = $loadableProperty->getProperty();
            $propertyTypes = $loadableProperty->getTypes();

            $value = $this->settings->get(
                $this->settingsPrefix . $property->getName(),
                self::DEFAULT_VALUE
            );

            if ($value === self::DEFAULT_VALUE) {
                $value = $defaultValues[$property->getName()];
            }

            $valueType = gettype($value);
            //convert
            $valueType = $valueType === "integer" ? "int" : $valueType;
            $valueType = $valueType === "boolean" ? "bool" : $valueType;

            if (!$this->checkPropertyType($valueType, $propertyTypes)) {
                if (is_string($value)) {
                    if (in_array("array", $propertyTypes, true)) {
                        try {
                            $value = @unserialize($value, ['allowed_classes' => false]);
                        } catch (TypeError $e) {
                            $value = false;
                        }
                    }

                    $failedTypeConvertCount = 0;
                    foreach ($propertyTypes as $propertyType) {
                        if (!settype($value, $propertyType)) {
                            $failedTypeConvertCount++;
                        }
                    }

                    if ($failedTypeConvertCount === count($propertyTypes)) {
                        $value = false;
                    }
                }

                if (!$this->checkPropertyType(gettype($value), $propertyTypes)) {
                    $properties->addUnloadable((new UnloadableProperty())
                        ->setProperty($property)
                        ->setTypes($propertyTypes)
                        ->setException(new ConfigLoadInvalidTypeException(gettype($value), $valueType)));
                    continue;
                }
            }
            $property->setAccessible(true);
            $property->setValue($this, $value);
        }

        if ($properties->hasUnloadable()) {
            throw new ConfigLoadException($properties->getUnloadable());
        }

        return $this;
    }

    private function checkPropertyType(string $type, array $typesToCheck) : ?string
    {
        foreach ($typesToCheck as $typeToCheck) {
            if ($type === $typeToCheck) {
                return $typeToCheck;
            }
        }

        return null;
    }

    private function getSupportedPrimitivePropertyTypes() : array
    {
        return [
            "string",
            "integer",
            "int",
            "float",
            "bool",
            "boolean",
            "null",
            "array"
        ];
    }

    /**
     * @param ReflectionClass $refClass
     * @return Properties
     * @throws ReflectionException
     */
    private function getProperties(ReflectionClass $refClass) : Properties
    {
        $properties = new Properties();
        $defaultProperties = $this->getDefaultValues();

        foreach ($refClass->getProperties() as $property) {
            $foundTypes = [];
            if (version_compare(PHP_VERSION, '7.4.0') >= 0 && $property->getType() !== null) {
                $typeReflection = $property->getType();
                $foundTypes[] = $typeReflection->getName();
                if ($typeReflection->allowsNull()) {
                    $foundTypes[] = "null";
                }
            } else {
                $docComment = $property->getDocComment();
                if ($docComment) {
                    preg_match("/@var ([a-zA-Z0-9, ()_].*)/", $property->getDocComment(), $docComments);
                    foreach (explode("|", $docComments[1]) as $type) {
                        $foundTypes[] = $type;
                    }
                } else {
                    $foundTypes[] = gettype($defaultProperties[$property->getName()]);
                }
            }

            $isNotLoadable = false;

            if ($foundTypes === []) {
                $isNotLoadable = true;
            }

            $convertedTypes = [];
            foreach ($foundTypes as $type) {
                $type = $type === "integer" ? "int" : $type;
                $type = $type === "boolean" ? "bool" : $type;
                $convertedTypes[] = $type;
            }
            $foundTypes = $convertedTypes;

            $supportedTypes = $this->getSupportedPrimitivePropertyTypes();

            foreach ($foundTypes as $type) {
                if (!in_array($type, $supportedTypes, true)) {
                    $isNotLoadable = true;
                    break;
                }
            }

            if ($isNotLoadable) {
                $properties->addUnloadable((new UnloadableProperty())
                    ->setProperty($property)
                    ->setTypes($foundTypes)
                    ->setException(new ConfigLoadNonPrimitiveDataTypeDetected()));
            }

            $properties->addLoadable((new LoadableProperty())
                ->setProperty($property)
                ->setTypes($foundTypes));
        }
        return $properties;
    }

    public function clean(UnloadableProperty $unloadableProperty) : bool
    {
        return (bool) $this->settings->delete($this->settingsPrefix . $unloadableProperty->getProperty()->getName());
    }
}
