<?php declare(strict_types=1);
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
 * @package ILIAS\Plugin\ChatClientInterface\Model
 * @author  Marvin Beym <mbeym@databay.de>
 */
class ConfigBase
{
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

    public function toArray() : array
    {
        $data = [];
        foreach ($this->getProperties(new ReflectionClass($this))->getLoadable() as $loadableProperty) {
            $property = $loadableProperty->getProperty();
            $property->setAccessible(true);
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
            $propertyType = $loadableProperty->getType();

            $value = $this->settings->get(
                $this->settingsPrefix . $property->getName(),
                $defaultValues[$property->getName()]
            );

            $valueType = gettype($value);

            if (!$this->checkPropertyType($valueType, $propertyType)) {
                if (is_string($value)) {
                    if ($this->checkPropertyType($propertyType, "array")) {
                        try {
                            $value = @unserialize($value, ['allowed_classes' => false]);
                        } catch (TypeError $e) {
                            $value = false;
                        }
                    } elseif (!settype($value, $propertyType)) {
                        $value = false;
                    }
                }

                if (!$this->checkPropertyType(gettype($value), $propertyType)) {
                    $properties->addUnloadable((new UnloadableProperty())
                        ->setProperty($property)
                        ->setType($propertyType)
                        ->setException(new ConfigLoadInvalidTypeException($propertyType, $valueType)));
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

    private function checkPropertyType(string $type, string $typeToCheck) : bool
    {
        $intTypes = ["int", "integer"];
        $boolTypes = ["bool", "boolean"];

        if (in_array($typeToCheck, $intTypes, true) && in_array($type, $intTypes, true)) {
            return true;
        }
        if (in_array($typeToCheck, $boolTypes, true) && in_array($type, $boolTypes, true)) {
            return true;
        }

        return $type === $typeToCheck;
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
     */
    private function getProperties(ReflectionClass $refClass) : Properties
    {
        $properties = new Properties();
        $defaultProperties = $this->getDefaultValues();

        foreach ($refClass->getProperties() as $property) {
            $docComment = $property->getDocComment();
            if ($docComment) {
                preg_match("/@var ([a-zA-Z0-9, ()_].*)/", $property->getDocComment(), $docComments);
                $type = $docComments[1];
            } else {
                $type = gettype($defaultProperties[$property->getName()]);
            }

            if (!$type || !in_array($type, $this->getSupportedPrimitivePropertyTypes(), true)) {
                $properties->addUnloadable((new UnloadableProperty())
                    ->setProperty($property)
                    ->setType($type)
                    ->setException(new ConfigLoadNonPrimitiveDataTypeDetected()));
                continue;
            }

            $properties->addLoadable((new LoadableProperty())
                ->setProperty($property)
                ->setType($type));
        }
        return $properties;
    }

    public function clean(UnloadableProperty $unloadableProperty) : bool
    {
        return (bool) $this->settings->delete($this->settingsPrefix . $unloadableProperty->getProperty()->getName());
    }
}
