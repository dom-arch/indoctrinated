<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Indoctrinated\Entity;

use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Common\Util\Inflector;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Indoctrinated\Db;
use Indoctrinated\Repository;

/**
 * A Doctrine's EntityGenerator hack
 *  - Provides an init($value) method
 *    It only sets if the property value is null
 *
 *  - Respects the column names style
 *
 *
 * @link    www.doctrine-project.org
 * @since   2.0
 * @author  Benjamin Eberlei <kontakt@beberlei.de>
 * @author  Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author  Jonathan Wage <jonwage@gmail.com>
 * @author  Roman Borschel <roman@code-factory.org>
 * @author  Lcf.vs <michael.rouges@gmail.com>
 */
class Generator
    extends \Doctrine\ORM\Tools\EntityGenerator
{
    private $_tables;

    /**
     * @var string
     */
    protected $fieldVisibility = 'protected';

    /**
     * @var string
     */
    protected static $validatorTemplate =
        '<?php

<namespace>

<entityValidatorName>
<spaces>extends \\Indoctrinated\Entity\Validator
{
}';

    /**
     * @var string
     */
    protected static $traitTemplate =
        '<?php

<namespace>

<entityTraitName>
{
<spaces><entityTraitBody>
}';

    /**
     * @var string
     */
    protected static $classTemplate =
        '<?php

<namespace>

<useStatement>

<entityAnnotation>
<entityClassName>
{
<spaces><entityTraitName>;

<entityBody>
}';

    /**
     * @var string
     */
    protected static $getIdMethodTemplate =
        '/**
 * <description>
 *
 * @return <variableType>
 */
public function <methodName>()
{
<spaces>return $this-><fieldName>;
}';

    /**
     * @var string
     */
    protected static $getMethodTemplate =
        '/**
 * <description>
 *
 * @param $<variableName><variableDefault>
 *
 * @return <variableType>
 */
public function <methodName>(
<spaces>$<variableName><variableDefault>
)
{
<spaces>if ($this-><fieldName> !== null) {
<spaces><spaces>return $this-><fieldName>;
<spaces>}

<spaces>return $<variableName>;
}';

    /**
     * @var string
     */
    protected static $initMethodTemplate =
        '/**
 * <description>
 *
 * @param <variableType> $<variableName>
 *
 * @return <entity>
 */
public function <methodName>(
<spaces><methodTypeHint> $<variableName><variableDefault>
)
{
<spaces>if ($this-><fieldName> !== null) {
<spaces><spaces>return $this;
<spaces>}

<spaces>return $this-><setterName>($<variableName>);
}';

    /**
     * @var string
     */
    protected static $setMethodTemplate =
        '/**
 * <description>
 *
 * @param <variableType> $<variableName>
 *
 * @return <entity>
 */
public function <methodName>(
<spaces><methodTypeHint> $<variableName><variableDefault>
)
{
<spaces>$this-><fieldName> = $<variableName>;

<spaces>return $this;
}';

    /**
     * @var string
     */
    protected static $addMethodTemplate =
        '/**
 * <description>
 *
 * @param <variableType> $<variableName>
 *
 * @return <entity>
 */
public function <methodName>(
<spaces><methodTypeHint>$<variableName>
)
{
<spaces>$this-><fieldName>[] = $<variableName>;

<spaces>return $this;
}';

    /**
     * @var string
     */
    protected static $removeMethodTemplate =
        '/**
 * <description>
 *
 * @param <variableType> $<variableName>
 */
public function <methodName>(
<spaces><methodTypeHint>$<variableName>
)
{
<spaces>$this-><fieldName>->removeElement($<variableName>);
}';

    public function __construct()
    {
        $tables = [];

        $schema_manager = Db::getEntityManager()
            ->getConnection()
            ->getSchemaManager();

        foreach ($schema_manager->listTableNames() as $table_name) {
            $key = strtolower($table_name);
            $tables[$key] = [
                'name' => $table_name
            ];

            $columns = [];

            foreach ($schema_manager->listTableColumns($table_name) as $column) {
                $column_name = $column->getName();

                $columns[strtolower($column_name)] = [
                    'name' => $column_name
                ];
            }

            $tables[$key]['columns'] = $columns;
        }

        $this->_tables = $tables;

        parent::__construct();
    }

    private function _getEntityName($entity_name)
    {
        $table = $this->_tables[strtolower($entity_name)];

        if (!$table) {
            return null;
        }

        return $table['name'];
    }

    private function _getColumnName($entity_name, $column_name)
    {
        $table = $this->_tables[strtolower($entity_name)];

        if (!$table) {
            return null;
        }

        $column = $table['columns'][strtolower($column_name)] ?? null;

        if (!$column) {

            echo debug_backtrace()[1]['function'];exit;
            return null;
        }

        return $column['name'];
    }

    /**
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     */
    protected function generateEntityAnnotation(ClassMetadataInfo $metadata)
    {
        $prefix = '@' . $this->annotationsPrefix;

        if ($metadata->isEmbeddedClass) {
            return $prefix . 'Embeddable';
        }

        // todo remove this hack

        $metadata->customRepositoryClassName = 'Repositories\\' . $this->getClassName($metadata);

        $customRepository = $metadata->customRepositoryClassName
            ? '(repositoryClass="' . $metadata->customRepositoryClassName . '")'
            : '';

        return $prefix . ($metadata->isMappedSuperclass ? 'MappedSuperclass' : 'Entity') . $customRepository;
    }

    /**
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     */
    public function generateEntityTrait(ClassMetadataInfo $metadata)
    {
        $placeHolders = array(
            '<namespace>',
            '<entityTraitName>',
            '<entityTraitBody>'
        );

        $replacements = array(
            $this->generateEntityTraitNamespace($metadata),
            'trait ' . $this->getClassName($metadata),
            $this->generateEntityTraitBody($metadata)
        );

        $code = str_replace($placeHolders, $replacements, static::$traitTemplate) . "\n";

        return str_replace('<spaces>', $this->spaces, $code);
    }

    /**
     * @param ClassMetadataInfo $metadata
     * @return string
     */
    protected function generateEntityTraitNamespace(ClassMetadataInfo $metadata)
    {
        $namespace = '';

        if ($this->hasNamespace($metadata)) {
            $namespace = $this->getNamespace($metadata) .'\\';
        }

        return 'namespace ' . $namespace . 'Traits;';
    }

    /**
     * Generates a PHP5 Doctrine 2 entity class from the given ClassMetadataInfo instance.
     *
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     */
    public function generateEntityClass(ClassMetadataInfo $metadata)
    {
        $placeHolders = array(
            '<namespace>',
            '<useStatement>',
            '<entityAnnotation>',
            '<entityClassName>',
            '<entityTraitName>',
            '<entityBody>'
        );

        $replacements = array(
            $this->generateEntityNamespace($metadata),
            $this->generateEntityUse(),
            $this->generateEntityDocBlock($metadata),
            $this->generateEntityClassName($metadata),
            'use Traits\\' . $this->getClassName($metadata),
            $this->generateEntityBody($metadata)
        );

        $code = str_replace($placeHolders, $replacements, static::$classTemplate) . "\n";

        return str_replace('<spaces>', $this->spaces, $code);
    }

    /**
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     */
    protected function generateEntityTraitBody(ClassMetadataInfo $metadata)
    {
        $lines = [];
        $lines[] = 'protected static $printables = [';
        $names = [];

        foreach ($metadata->fieldMappings as $fieldMapping) {
            $names[] = $this->spaces . $this->spaces . '\'' . $fieldMapping['columnName'] . '\'';
        }

        $lines[] = implode(",\n", $names);

        $lines[] = $this->spaces . '];';

        return implode("\n", $lines);
    }

    /**
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     */
    protected function generateEntityStubMethods(ClassMetadataInfo $metadata)
    {
        $methods = array();

        foreach ($metadata->fieldMappings as $fieldMapping) {
            if (isset($fieldMapping['declaredField']) &&
                isset($metadata->embeddedClasses[$fieldMapping['declaredField']])
            ) {
                continue;
            }

            if (( ! isset($fieldMapping['id']) ||
                    ! $fieldMapping['id'] ||
                    $metadata->generatorType == ClassMetadataInfo::GENERATOR_TYPE_NONE
                ) && (! $metadata->isEmbeddedClass || ! $this->embeddablesImmutable)
            ) {
                if ($code = $this->generateEntityStubMethod($metadata, 'set', $fieldMapping['fieldName'], $fieldMapping['type'])) {
                    $methods[] = $code;
                }
                if ($code = $this->generateEntityStubMethod($metadata, 'init', $fieldMapping['fieldName'], $fieldMapping['type'])) {
                    $methods[] = $code;
                }
            }

            if ($code = $this->generateEntityStubMethod($metadata, 'get', $fieldMapping['fieldName'], $fieldMapping['type'])) {
                $methods[] = $code;
            }
        }

        foreach ($metadata->embeddedClasses as $fieldName => $embeddedClass) {
            if (isset($embeddedClass['declaredField'])) {
                continue;
            }

            if ( ! $metadata->isEmbeddedClass || ! $this->embeddablesImmutable) {
                if ($code = $this->generateEntityStubMethod($metadata, 'set', $fieldName, $embeddedClass['class'])) {
                    $methods[] = $code;
                }

                if ($code = $this->generateEntityStubMethod($metadata, 'init', $fieldName, $embeddedClass['class'])) {
                    $methods[] = $code;
                }
            }

            if ($code = $this->generateEntityStubMethod($metadata, 'get', $fieldName, $embeddedClass['class'])) {
                $methods[] = $code;
            }
        }

        foreach ($metadata->associationMappings as $associationMapping) {
            if ($associationMapping['type'] & ClassMetadataInfo::TO_ONE) {
                $nullable = $this->isAssociationIsNullable($associationMapping) ? 'null' : null;
                if ($code = $this->generateEntityStubMethod($metadata, 'set', $associationMapping['fieldName'], $associationMapping['targetEntity'], $nullable)) {
                    $methods[] = $code;
                }
                if ($code = $this->generateEntityStubMethod($metadata, 'init', $associationMapping['fieldName'], $associationMapping['targetEntity'], $nullable)) {
                    $methods[] = $code;
                }
                if ($code = $this->generateEntityStubMethod($metadata, 'get', $associationMapping['fieldName'], $associationMapping['targetEntity'])) {
                    $methods[] = $code;
                }
            } elseif ($associationMapping['type'] & ClassMetadataInfo::TO_MANY) {
                if ($code = $this->generateEntityStubMethod($metadata, 'add', $associationMapping['fieldName'], $associationMapping['targetEntity'])) {
                    $methods[] = $code;
                }
                if ($code = $this->generateEntityStubMethod($metadata, 'remove', $associationMapping['fieldName'], $associationMapping['targetEntity'])) {
                    $methods[] = $code;
                }
                if ($code = $this->generateEntityStubMethod($metadata, 'get', $associationMapping['fieldName'], 'Doctrine\Common\Collections\Collection')) {
                    $methods[] = $code;
                }
            }
        }

        return implode("\n\n", $methods);
    }

    protected function generateEntityFieldMappingProperties(ClassMetadataInfo $metadata)
    {
        $lines = array();

        foreach ($metadata->fieldMappings as $fieldMapping) {
            $column_name = $this->_getColumnName($metadata->getName(), $fieldMapping['fieldName']);

            if ($this->hasProperty($column_name, $metadata) ||
                $metadata->isInheritedField($column_name) ||
                (
                    isset($fieldMapping['declaredField']) &&
                    isset($metadata->embeddedClasses[$fieldMapping['declaredField']])
                )
            ) {
                continue;
            }

            $lines[] = $this->generateFieldMappingPropertyDocBlock($fieldMapping, $metadata);
            $lines[] = $this->spaces . $this->fieldVisibility . ' $' . $column_name
                . (isset($fieldMapping['options']['default']) ? ' = ' . var_export($fieldMapping['options']['default'], true) : null) . ";\n";
        }

        return implode("\n", $lines);
    }

    /**
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     */

    protected function generateEntityAssociationMappingProperties(ClassMetadataInfo $metadata)
    {
        $lines = array();

        foreach ($metadata->associationMappings as $mapping) {
            if ($mapping['type'] === 2) {
                $property_name = lcfirst(Inflector::singularize($this->_getEntityName($mapping['targetEntity'])));
                $property_identifier = $property_name . 'Id';

                if (!$this->hasProperty($property_identifier, $metadata)) {
                    $lines[] = '    /**';
                    $lines[] = '     * @var integer';
                    $lines[] = '     *';
                    $lines[] = '     * @ORM\Column(name="' . $property_identifier . '", type="integer", nullable=true)';
                    $lines[] = '     */';
                    $lines[] = '    protected $' . $property_identifier . ';' . "\n";
                }
            } else {
                $property_name = lcfirst($this->_getEntityName($mapping['targetEntity']));
            }

            if ($this->hasProperty($property_name, $metadata)) {
                continue;
            }

            $lines[] = $this->generateAssociationMappingPropertyDocBlock($mapping, $metadata);
            $lines[] = $this->spaces . $this->fieldVisibility . ' $' . $property_name
                . ($mapping['type'] == 'manyToMany' ? ' = array()' : null) . ";\n";
        }

        return implode("\n", $lines);
    }

    /**
     * @param ClassMetadataInfo $metadata
     * @param string            $type
     * @param string            $fieldName
     * @param string|null       $typeHint
     * @param string|null       $defaultValue
     *
     * @return string
     */
    protected function generateEntityStubMethod(ClassMetadataInfo $metadata, $type, $fieldName, $typeHint = null,  $defaultValue = null)
    {
        $methodName = $type . Inflector::classify($fieldName);
        $variableName = Inflector::camelize($fieldName);
        if (in_array($type, array("add", "remove"))) {
            $methodName = Inflector::singularize($methodName);
            $variableName = Inflector::singularize($variableName);
        }

        if ($this->hasMethod($methodName, $metadata)) {
            return '';
        }

        $this->staticReflection[$metadata->name]['methods'][] = strtolower($methodName);

        $var = sprintf('%sMethodTemplate', $type);
        $template = static::$$var;

        $methodTypeHint = null;
        $types          = Type::getTypesMap();
        $variableType   = $typeHint ? $this->getType($typeHint) : null;

        if ($typeHint && ! isset($types[$typeHint])) {
            $variableType   =  '\\' . ltrim($variableType, '\\');
            $methodTypeHint =  '\\' . $typeHint . ' ';
        }

        $columnName = $variableName;
        $naming_strategy = new UnderscoreNamingStrategy();
        $variableName = $naming_strategy->propertyToColumnName($variableName);

        $replacements = array(
            '<description>'       => ucfirst($type) . ' ' . $variableName,
            '<methodTypeHint>'    => $methodTypeHint,
            '<variableType>'      => $variableType,
            '<variableName>'      => $variableName,
            '<methodName>'        => $methodName,
            '<fieldName>'         => $fieldName,
            '<variableDefault>'   => ($defaultValue !== null || $type === 'get') ? (' = '.($defaultValue ?? 'null')) : '',
            '<entity>'            => $this->getClassName($metadata)
        );

        if ($type === 'init') {
            $replacements['<setterName>'] = 'set' . ucfirst($columnName);
        }

        $method = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );

        return $this->prefixCodeWithSpaces($method);
    }

    /**
     * @param array             $associationMapping
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     */
    protected function generateAssociationMappingPropertyDocBlock(array $associationMapping, ClassMetadataInfo $metadata)
    {
        $lines = array();
        $lines[] = $this->spaces . '/**';

        if ($associationMapping['type'] & ClassMetadataInfo::TO_MANY) {
            $lines[] = $this->spaces . ' * @var \Doctrine\Common\Collections\Collection';
        } else {
            $target_entity = $this->_getEntityName($associationMapping['targetEntity']);

            $lines[] = $this->spaces . ' * @var \\' . $target_entity;
        }

        if ($this->generateAnnotations) {
            $lines[] = $this->spaces . ' *';

            if (isset($associationMapping['id']) && $associationMapping['id']) {
                $lines[] = $this->spaces . ' * @' . $this->annotationsPrefix . 'Id';

                if ($generatorType = $this->getIdGeneratorTypeString($metadata->generatorType)) {
                    $lines[] = $this->spaces . ' * @' . $this->annotationsPrefix . 'GeneratedValue(strategy="' . $generatorType . '")';
                }
            }

            $type = null;
            switch ($associationMapping['type']) {
                case ClassMetadataInfo::ONE_TO_ONE:
                    $type = 'OneToOne';
                    break;
                case ClassMetadataInfo::MANY_TO_ONE:
                    $type = 'ManyToOne';
                    break;
                case ClassMetadataInfo::ONE_TO_MANY:
                    $type = 'OneToMany';
                    break;
                case ClassMetadataInfo::MANY_TO_MANY:
                    $type = 'ManyToMany';
                    break;
            }
            $typeOptions = array();

            if (isset($associationMapping['targetEntity'])) {
                $target_entity = $this->_getEntityName($associationMapping['targetEntity']);

                $typeOptions[] = 'targetEntity="' . $target_entity . '"';
            }

            if (isset($associationMapping['inversedBy'])) {
                foreach ($associationMapping['joinTable']['joinColumns'] as $column) {
                    $column_name = $column['name'];

                    if (strtolower($column_name) === $associationMapping['inversedBy']) {
                        break;
                    }
                }

                $typeOptions[] = 'inversedBy="' . $column_name . '"';
            }

            if (isset($associationMapping['mappedBy'])) {
                $column_name = lcfirst(Inflector::singularize($associationMapping['sourceEntity'])) . 'Id';

                $typeOptions[] = 'mappedBy="' . $column_name . '"';
            }

            if ($associationMapping['cascade']) {
                $cascades = array();

                if ($associationMapping['isCascadePersist']) $cascades[] = '"persist"';
                if ($associationMapping['isCascadeRemove']) $cascades[] = '"remove"';
                if ($associationMapping['isCascadeDetach']) $cascades[] = '"detach"';
                if ($associationMapping['isCascadeMerge']) $cascades[] = '"merge"';
                if ($associationMapping['isCascadeRefresh']) $cascades[] = '"refresh"';

                if (count($cascades) === 5) {
                    $cascades = array('"all"');
                }

                $typeOptions[] = 'cascade={' . implode(',', $cascades) . '}';
            }

            if (isset($associationMapping['orphanRemoval']) && $associationMapping['orphanRemoval']) {
                $typeOptions[] = 'orphanRemoval=' . ($associationMapping['orphanRemoval'] ? 'true' : 'false');
            }

            if (isset($associationMapping['fetch']) && $associationMapping['fetch'] !== ClassMetadataInfo::FETCH_LAZY) {
                $fetchMap = array(
                    ClassMetadataInfo::FETCH_EXTRA_LAZY => 'EXTRA_LAZY',
                    ClassMetadataInfo::FETCH_EAGER      => 'EAGER',
                );

                $typeOptions[] = 'fetch="' . $fetchMap[$associationMapping['fetch']] . '"';
            }

            $lines[] = $this->spaces . ' * @' . $this->annotationsPrefix . '' . $type . '(' . implode(', ', $typeOptions) . ')';

            if (isset($associationMapping['joinColumns']) && $associationMapping['joinColumns']) {
                $lines[] = $this->spaces . ' * @' . $this->annotationsPrefix . 'JoinColumns({';

                $joinColumnsLines = array();

                foreach ($associationMapping['joinColumns'] as $joinColumn) {
                    if ($joinColumnAnnot = $this->generateJoinColumnAnnotation($joinColumn)) {
                        $joinColumnsLines[] = $this->spaces . ' *   ' . $joinColumnAnnot;
                    }
                }

                $lines[] = implode(",\n", $joinColumnsLines);
                $lines[] = $this->spaces . ' * })';
            }

            if (isset($associationMapping['joinTable']) && $associationMapping['joinTable']) {
                $joinTable = array();
                $entity_name = $this->_getEntityName($associationMapping['joinTable']['name']);
                $joinTable[] = 'name="' . $entity_name . '"';

                if (isset($associationMapping['joinTable']['schema'])) {
                    $joinTable[] = 'schema="' . $associationMapping['joinTable']['schema'] . '"';
                }

                $lines[] = $this->spaces . ' * @' . $this->annotationsPrefix . 'JoinTable(' . implode(', ', $joinTable) . ',';
                $lines[] = $this->spaces . ' *   joinColumns={';

                $joinColumnsLines = array();

                foreach ($associationMapping['joinTable']['joinColumns'] as $joinColumn) {
                    $joinColumnsLines[] = $this->spaces . ' *     ' . $this->generateJoinColumnAnnotation($joinColumn);
                }

                $lines[] = implode(",". PHP_EOL, $joinColumnsLines);
                $lines[] = $this->spaces . ' *   },';
                $lines[] = $this->spaces . ' *   inverseJoinColumns={';

                $inverseJoinColumnsLines = array();

                foreach ($associationMapping['joinTable']['inverseJoinColumns'] as $joinColumn) {
                    $inverseJoinColumnsLines[] = $this->spaces . ' *     ' . $this->generateJoinColumnAnnotation($joinColumn);
                }

                $lines[] = implode(",". PHP_EOL, $inverseJoinColumnsLines);
                $lines[] = $this->spaces . ' *   }';
                $lines[] = $this->spaces . ' * )';
            }

            if (isset($associationMapping['orderBy'])) {
                $lines[] = $this->spaces . ' * @' . $this->annotationsPrefix . 'OrderBy({';

                foreach ($associationMapping['orderBy'] as $name => $direction) {
                    $lines[] = $this->spaces . ' *     "' . $name . '"="' . $direction . '",';
                }

                $lines[count($lines) - 1] = substr($lines[count($lines) - 1], 0, strlen($lines[count($lines) - 1]) - 1);
                $lines[] = $this->spaces . ' * })';
            }
        }

        $lines[] = $this->spaces . ' */';

        return implode("\n", $lines);
    }

    /**
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     */
    protected function generateEntityConstructor(ClassMetadataInfo $metadata)
    {
        if ($this->hasMethod('__construct', $metadata)) {
            return '';
        }

        if ($metadata->isEmbeddedClass && $this->embeddablesImmutable) {
            return $this->generateEmbeddableConstructorAlias($metadata);
        }

        $collections = array();

        foreach ($metadata->associationMappings as $mapping) {
            if ($mapping['type'] & ClassMetadataInfo::TO_MANY) {
                $column_name = $this->_getEntityName($mapping['targetEntity']);
                $property_name = lcfirst(Inflector::pluralize($column_name));

                $collections[] = '$this->'.$property_name.' = new \Doctrine\Common\Collections\ArrayCollection();';
            }
        }

        if ($collections) {
            return $this->prefixCodeWithSpaces(str_replace("<collections>", implode("\n".$this->spaces, $collections), static::$constructorMethodTemplate));
        }

        return '';
    }

    /**
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     */
    private function generateEmbeddableConstructorAlias(ClassMetadataInfo $metadata)
    {
        $paramTypes = array();
        $paramVariables = array();
        $params = array();
        $fields = array();

        // Resort fields to put optional fields at the end of the method signature.
        $requiredFields = array();
        $optionalFields = array();

        foreach ($metadata->fieldMappings as $fieldMapping) {
            if (empty($fieldMapping['nullable'])) {
                $requiredFields[] = $fieldMapping;

                continue;
            }

            $optionalFields[] = $fieldMapping;
        }

        $fieldMappings = array_merge($requiredFields, $optionalFields);

        foreach ($metadata->embeddedClasses as $fieldName => $embeddedClass) {
            $paramType = '\\' . ltrim($embeddedClass['class'], '\\');
            $paramVariable = '$' . $fieldName;

            $paramTypes[] = $paramType;
            $paramVariables[] = $paramVariable;
            $params[] = $paramType . ' ' . $paramVariable;
            $fields[] = '$this->' . $fieldName . ' = ' . $paramVariable . ';';
        }

        foreach ($fieldMappings as $fieldMapping) {
            if (isset($fieldMapping['declaredField']) &&
                isset($metadata->embeddedClasses[$fieldMapping['declaredField']])
            ) {
                continue;
            }

            $paramTypes[] = $this->getType($fieldMapping['type']) . (!empty($fieldMapping['nullable']) ? '|null' : '');
            $param = '$' . $fieldMapping['fieldName'];
            $paramVariables[] = $param;

            if ($fieldMapping['type'] === 'datetime') {
                $param = $this->getType($fieldMapping['type']) . ' ' . $param;
            }

            if (!empty($fieldMapping['nullable'])) {
                $param .= ' = null';
            }

            $params[] = $param;

            $fields[] = '$this->' . $fieldMapping['fieldName'] . ' = $' . $fieldMapping['fieldName'] . ';';
        }

        $maxParamTypeLength = max(array_map('strlen', $paramTypes));
        $paramTags = array_map(
            function ($type, $variable) use ($maxParamTypeLength) {
                return '@param ' . $type . str_repeat(' ', $maxParamTypeLength - strlen($type) + 1) . $variable;
            },
            $paramTypes,
            $paramVariables
        );

        // Generate multi line constructor if the signature exceeds 120 characters.
        if (array_sum(array_map('strlen', $params)) + count($params) * 2 + 29 > 120) {
            $delimiter = "\n" . $this->spaces;
            $params = $delimiter . implode(',' . $delimiter, $params) . "\n";
        } else {
            $params = implode(', ', $params);
        }

        $replacements = array(
            '<paramTags>' => implode("\n * ", $paramTags),
            '<params>'    => $params,
            '<fields>'    => implode("\n" . $this->spaces, $fields),
        );

        $constructor = str_replace(
            array_keys($replacements),
            array_values($replacements),
            static::$embeddableConstructorMethodTemplate
        );

        return $this->prefixCodeWithSpaces($constructor);
    }
}
