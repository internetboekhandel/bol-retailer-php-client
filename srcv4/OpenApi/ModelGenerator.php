<?php


namespace Picqer\BolRetailerV4\OpenApi;

class ModelGenerator
{
    protected static $propTypeMapping = [
        'array' => 'array',
        'string' => 'string',
        'boolean' => 'bool',
        'integer' => 'int',
        'float' => 'float',
        'number' => 'float'
    ];

    protected $specs;

    public function __construct()
    {
        $this->specs = json_decode(file_get_contents(__DIR__ . '/apispec.json'), true);
    }

    static public function run()
    {
        $generator = new static;
        $generator->generateModels();
//        $generator->generateModel('Store');
//        $generator->generateModel('DeliveryOption');
//        $generator->generateModel('ShipmentRequest');
//        $generator->generateModel('ShippingLabelRequest');
//        $generator->generateModel('BulkProcessStatusRequest');
    }

    public function generateModels(): void
    {
        foreach ($this->specs['definitions'] as $type => $modelDefinition) {
            $this->generateModel($type);
        }
    }

    public function generateModel($type): void
    {

        $modelDefinition = $this->specs['definitions'][$type];
        $type = $this->getType('#/definitions/' . $type);

        echo $type . "...";

        $code = [];
        $code[] = '<?php';
        $code[] = '';
        $code[] = sprintf('namespace %s;', $this->getModelNamespace());
        $code[] = '';
        $code[] = '// This class is auto generated by OpenApi\ModelGenerator';
        $code[] = sprintf('class %s extends AbstractModel', $type);
        $code[] = '{';
        // TODO Add enums
        $this->generateDefinition($modelDefinition, $code);
        $this->generateFields($modelDefinition, $code);
        $this->generateDateTimeGetters($modelDefinition, $code);
        $this->generateMonoFieldAccessors($modelDefinition, $code);
        $code[] = '}';
        $code[] = '';

        //print_r($modelDefinition);

        //echo implode("\n", $code);

        file_put_contents(__DIR__ . '/../Model/' . $type . '.php', implode("\n", $code));

        echo "ok\n";
    }

    protected function generateDefinition(array $modelDefinition, array &$code): void
    {
        $code[] = '    /**';
        $code[] = '     * Returns the definition of the model: an associative array with field names as key and';
        $code[] = '     * field definition as value. The field definition contains of';
        $code[] = '     * model: Model class or null if it is a scalar type';
        $code[] = '     * array: Boolean whether it is an array';
        $code[] = '     * @return array The model definition';
        $code[] = '     */';
        $code[] = '    public function getModelDefinition(): array';
        $code[] = '    {';
        $code[] = '        return [';

        foreach ($modelDefinition['properties'] as $name => $propDefinition) {
            $model = 'null';
            $array = 'false';

            if (isset($propDefinition['type'])) {
                if ($propDefinition['type'] == 'array') {
                    $array = 'true';
                    if (isset($propDefinition['items']['$ref'])) {
                        $model = $this->getType($propDefinition['items']['$ref']) . '::class';
                    }
                }
            } elseif (isset($propDefinition['$ref'])) {
                $model = $this->getType($propDefinition['$ref']) . '::class';
            } else {
                // TODO create exception class for this one
                throw new \Exception('Unknown property definition');
            }

            $code[] = sprintf('            \'%s\' => [ \'model\' => %s, \'array\' => %s ],', $name, $model, $array);
        }

        $code[] = '        ];';
        $code[] = '    }';
    }

    protected function generateFields(array $modelDefinition, array &$code): void
    {


        foreach ($modelDefinition['properties'] as $name => $propDefinition) {

            if (isset($propDefinition['type'])) {
                $propType = static::$propTypeMapping[$propDefinition['type']];
                if ($propType == 'array' && isset($propDefinition['items']['$ref'])) {
                    $propType = $this->getType($propDefinition['items']['$ref']) . '[]';
                }
            } elseif (isset($propDefinition['$ref'])) {
                $propType = $this->getType($propDefinition['$ref']);
            } else {
                // TODO create exception class for this one
                throw new \Exception('Unknown property definition');
            }

            $code[] = '';
            $code[] = '    /**';

            if (isset($propDefinition['description'])) {
                $code[] = sprintf('     * @var %s %s', $propType, $propDefinition['description']);
            } else {
                $code[] = sprintf('     * @var %s', $propType);
            }

            $code[] = '     */';

            if (isset($propDefinition['type']) && $propDefinition['type'] == 'array') {
                $code[] = sprintf('    public $%s = [];', $name);
            } else {
                $code[] = sprintf('    public $%s;', $name);
            }
        }
    }

    protected function generateDateTimeGetters(array $modelDefinition, array &$code): void
    {
        foreach ($modelDefinition['properties'] as $name => $propDefinition) {
            if (strpos($name, 'DateTime') === false) {
                continue;
            }

            $code[] = '';
            $code[] = sprintf('    public function get%s(): ?\DateTime', ucfirst($name));
            $code[] = '    {';
            $code[] = sprintf('        if (empty($this->%s)) {', $name);
            $code[] = '            return null;';
            $code[] = '        }';
            $code[] = '';
            $code[] = sprintf('        return \DateTime::createFromFormat(\DateTime::ATOM, $this->%s);', $name);
            $code[] = '    }';
        }
    }

    protected function generateMonoFieldAccessors(array $modelDefinition, array &$code): void
    {
        $monoFields = $this->getFieldsWithMonoFieldModelType($modelDefinition);

        foreach ($monoFields as $fieldName => $fieldProps) {
            if ($fieldProps['monoFieldType'] == 'array') {
                continue;
            }

            $accessorName = $fieldProps['monoFieldName'];
            $accessorFullName = $accessorName;

            if (strpos(strtolower($accessorName), substr(strtolower($fieldName), 0, -1)) === false) {
                $accessorFullName = $fieldName . ucfirst($accessorName);
            }

            $accessorTypePhp = static::$propTypeMapping[$fieldProps['monoFieldType']];
            $accessorTypeDoc = $accessorTypePhp;

            $code[] = '';

            if ($fieldProps['array']) {
                $code[] = '    /**';
                $code[] = sprintf('     * Returns an array with the %ss from %s.', $accessorName, $fieldName);
                $code[] = sprintf('     * @return %s[] %ss from %s.', $accessorTypeDoc, ucfirst($accessorName), $fieldName);
                $code[] = '     */';
                $code[] = sprintf('    public function get%ss(): array', ucfirst($accessorFullName));
                $code[] = '    {';
                $code[] = '        return array_map(function ($model) {';
                $code[] = sprintf('            return $model->%s;', $fieldProps['monoFieldName']);
                $code[] = sprintf('        }, $this->%s);', $fieldName);
                $code[] = '    }';
            } else {
                $code[] = '    /**';
                $code[] = sprintf('     * Returns %s from %s.', $accessorName, $fieldName);
                $code[] = sprintf('     * @return %s %s from %s.', $accessorTypeDoc, ucfirst($accessorName), $fieldName);
                $code[] = '     */';
                $code[] = sprintf('    public function get%s(): %s', ucfirst($accessorFullName), $accessorTypePhp);
                $code[] = '    {';
                $code[] = sprintf('        return $this->%s->%s;', $fieldName, $fieldProps['monoFieldName']);
                $code[] = '    }';
            }

            $code[] = '';

            if ($fieldProps['array']) {
                $code[] = '    /**';
                $code[] = sprintf('     * Sets %s by an array of %ss.', $fieldName, $accessorName);
                $code[] = sprintf('     * @param %s[] $%ss %ss for %s.', $accessorTypeDoc, $accessorName, ucfirst($accessorName), $fieldName);
                $code[] = '     */';
                $code[] = sprintf('    public function set%ss(array $%ss): void', ucfirst($accessorFullName), $accessorName);
                $code[] = '    {';
                $code[] = sprintf('        $this->%s = array_map(function ($%s) {', $fieldName, $fieldProps['monoFieldName']);
                $code[] = sprintf('            return %s::constructFromArray([\'%s\' => $%s]);', $fieldProps['fieldType'], $fieldProps['monoFieldName'], $fieldProps['monoFieldName']);
                $code[] = sprintf('        }, $%ss);', $accessorName);
                $code[] = '    }';
            } else {
                $code[] = '    /**';
                $code[] = sprintf('     * Sets %s by %s.', $fieldName, $accessorName);
                $code[] = sprintf('     * @param %s $%s %s for %s.', $accessorTypeDoc, $accessorName, ucfirst($accessorName), $fieldName);
                $code[] = '     */';
                $code[] = sprintf('    public function set%s(%s $%s): void', ucfirst($accessorFullName), $accessorTypePhp, $accessorName);
                $code[] = '    {';
                $code[] = sprintf('        $this->%s = %s::constructFromArray([\'%s\' => $%s]);', $fieldName, $fieldProps['fieldType'], $fieldProps['monoFieldName'], $fieldProps['monoFieldName']);
                $code[] = '    }';
            }

            if ($fieldProps['array']) {
                $code[] = '';
                $code[] = '    /**';
                $code[] = sprintf('     * Adds a new %s to %s by %s.', $fieldProps['fieldType'], $fieldName, $accessorName);
                $code[] = sprintf('     * @param %s $%s %s for the %s to add.', $accessorTypeDoc, $accessorName, ucfirst($accessorName), $fieldProps['fieldType']);
                $code[] = '     */';
                $code[] = sprintf('    public function add%s(%s $%s): void', ucfirst($accessorFullName), $accessorTypePhp, $accessorName);
                $code[] = '    {';
                $code[] = sprintf('        $this->%s[] = %s::constructFromArray([\'%s\' => $%s]);', $fieldName, $fieldProps['fieldType'], $fieldProps['monoFieldName'], $fieldProps['monoFieldName']);
                $code[] = '    }';
            }
        }

    }



    protected function getType(string $ref): string
    {
        //strip #/definitions/
        $type = substr($ref, strrpos($ref, '/') + 1);

        // There are some weird types like 'delivery windows for inbound shipments.', uppercase and concat
        $type = str_replace(['.', ','], '', $type);
        $words = explode(' ', $type);
        $words = array_map(function ($word) {
            return ucfirst($word);
        }, $words);
        $type = implode('', $words);

        // Classname 'Return' is not allowed in php <= 7
        if ($type == 'Return') {
            $type = 'ReturnObject';
        }

        return $type;
    }

    protected function getModelNamespace(): string
    {
        $namespace = substr(__NAMESPACE__, 0, strrpos(__NAMESPACE__, '\\'));
        return $namespace . '\Model';
    }

    protected function getFieldsWithMonoFieldModelType(array $modelDefinition): array
    {
        $fields = [];

        foreach ($modelDefinition['properties'] as $propName => $propDefinition) {
            $isArray = null;
            if (isset($propDefinition['$ref'])) {
                $propType = $this->getType($propDefinition['$ref']);
                $isArray = false;
            } elseif (isset($propDefinition['items']['$ref'])) {
                $propType = $this->getType($propDefinition['items']['$ref']);
                $isArray = true;
            } else {
                $propType = $propDefinition['type'];
            }

            if (!isset($this->specs['definitions'][$propType])) {
                continue;
            }

            if (count($this->specs['definitions'][$propType]['properties']) != 1) {
                continue;
            }

            $subPropName = array_keys($this->specs['definitions'][$propType]['properties'])[0];
            $subPropType = $this->specs['definitions'][$propType]['properties'][$subPropName]['type'];

            $fields[$propName] = [
                'fieldType' => $propType,
                'monoFieldName' => $subPropName,
                'monoFieldType' => $subPropType,
                'array' => $isArray,
            ];
        }

        return $fields;
    }
}
