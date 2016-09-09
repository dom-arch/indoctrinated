<?php
/**
 * @author : Lcf.vs
 * @link: https://github.com/Lcfvs
 */
namespace Indoctrinated;

use DateTime;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\QueryBuilder;
use stdClass;

use Doctrine\ORM\Mapping as ORM;
use Indoctrinated\Db;

/**
 * @ORM\HasLifecycleCallbacks
 * @ORM\MappedSuperclass
 */
abstract class Entity
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="createdAt", type="datetime", nullable=false)
     */
    protected $createdAt;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="updatedAt", type="datetime", nullable=true)
     */
    protected $updatedAt;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="archivedAt", type="datetime", nullable=true)
     */
    protected $archivedAt;

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    public function setCreatedAt(
        $created_at
    ) : self
    {
        $this->createdAt = $created_at;

        return $this;
    }

    public function initCreatedAt(
        $created_at
    ) : self
    {
        if ($this->createdAt !== null) {
            return $this;
        }

        return $this->setCreatedAt($created_at);
    }

    public function getCreatedAt(
        $created_at = null
    )
    {
        if ($this->createdAt !== null) {
            return $this->createdAt;
        }

        return $created_at;
    }

    public function setUpdatedAt(
        $updated_at
    ) : self
    {
        $this->updatedAt = $updated_at;

        return $this;
    }

    public function initUpdatedAt(
        $updated_at
    ) : self
    {
        if ($this->updatedAt !== null) {
            return $this;
        }

        return $this->setUpdatedAt($updated_at);
    }

    public function getUpdatedAt(
        $updated_at = null
    )
    {
        if ($this->updatedAt !== null) {
            return $this->updatedAt;
        }

        return $updated_at;
    }

    public function setArchivedAt(
        $archived_at
    ) : self
    {
        $this->archivedAt = $archived_at;

        return $this;
    }

    public function initArchivedAt(
        $archived_at
    ) : self
    {
        if ($this->archivedAt !== null) {
            return $this;
        }

        return $this->setArchivedAt($archived_at);
    }

    public function getArchivedAt(
        $archived_at = null
    )
    {
        if ($this->archivedAt !== null) {
            return $this->archivedAt;
        }

        return $archived_at;
    }

    public static function getEntityName() : string
    {
        return Db::getEntityManager()
            ->getClassMetadata(static::class)
            ->getTableName();
    }

    public static function getEntityRepository() : Repository
    {
        return Db::getEntityManager()
            ->getRepository(static::class);
    }

    public static function getSelectQueryBuilder(
        bool $existing = true
    ) : QueryBuilder
    {
        $alias = strtolower(static::class);

        $builder = Db::getEntityManager()
            ->createQueryBuilder()
            ->select($alias)
            ->from(static::class, $alias);

        if ($existing) {
            $builder->andWhere($builder->expr()->isNull($alias . '.archivedAt'));
        }

        return $builder;
    }

    public static function getCountQueryBuilder(
        bool $existing = true
    ) : QueryBuilder
    {
        $alias = strtolower(static::class);
        $builder = Db::getEntityManager()
            ->createQueryBuilder();

        $builder
            ->select($builder->expr()->count($alias . '.id'))
            ->from(static::class, $alias);

        if ($existing) {
            $builder->andWhere($builder->expr()->isNull($alias . '.archivedAt'));
        }

        return $builder;
    }

    public function getOriginalEntityData() : array
    {
        return Db::getEntityManager()
            ->getUnitOfWork()
            ->getOriginalEntityData($this);
    }

    public static function getFieldNames() : array
    {
        $properties = Db::getEntityManager()
            ->getClassMetadata(static::getEntityName())
            ->getFieldNames();

        sort($properties);

        return $properties;
    }

    public static function fromArray(
        array $data
    ) : self
    {
        return static::fromObject((object) $data);
    }

    public static function fromObject(
        stdClass $object
    ) : self
    {
        $properties = static::getFieldNames();

        $instance = static::getEntityRepository()
            ->one();

        foreach ($object as $property => $value) {
            $method_name = 'init' . ucfirst($property);
            $method = [$instance, $method_name];

            if (!in_array($property, $properties)) {
                continue;
            }

            if (!in_array($property, static::$printables)) {
                continue;
            }

            if (!is_callable($method, true)) {
                continue;
            }

            $instance->{$method_name}($value);
        }

        return $instance;
    }

    public function fill(
        stdClass $object
    )
    {
        $properties = static::getFieldNames();

        foreach ($object as $property => $value) {
            $method_name = 'set' . ucfirst($property);
            $method = [$this, $method_name];

            if (!in_array($property, $properties)) {
                continue;
            }

            if (!in_array($property, static::$printables)) {
                continue;
            }

            if (!is_callable($method, true)) {
                continue;
            }

            $this->{$method_name}($value);
        }

        return $this;
    }

    public static function create() : self
    {
        return new static();
    }

    public function save() : self
    {
        $entity_manager = Db::getEntityManager();
        $entity_manager->persist($this);
        $entity_manager->flush();

        return $this;
    }

    public function archive() : self
    {

        $time = Db::getTime() ?? new DateTime();

        return $this->setArchivedAt($time)
            ->save();
    }

    public function toArray() : array
    {
        return json_decode(json_encode($this->toObject()), true);
    }

    public function toObject() : stdClass
    {
        $properties = static::$printables;

        $std = new stdClass();

        foreach ($properties as $property) {
            $method_name = 'get' . ucfirst($property);
            $method = [$this, $method_name];

            if (!is_callable($method, true)) {
                continue;
            }

            $value = $this->{$method_name}();

            if ($value === null) {
                continue;
            }

            if (is_array($value)) {
                $values = [];

                foreach ($value as $entity) {
                    $values[] = $entity->toObject();
                }

                $std->{$property} = $values;

                continue;
            }

            if (gettype($value) !== 'object') {
                $std->{$property} = $value;
            }

            if ($value instanceof DateTime) {
                $timestamp = $value->getTimestamp();
                $std->{$property} = gmdate('Y-m-d H:i:s', $timestamp);

                continue;
            }

            if ($value instanceof self) {
                $std->{$property} = $value->toObject();

                continue;
            }

            if ($value instanceof PersistentCollection) {
                $children = [];

                foreach ($value as $child_key => $child_value) {
                    $children[$child_key] = $child_value->toObject();
                }

                $std->{$property} = $children;

                continue;
            }

            $std->{$property} = $value;
        }

        return $std;
    }

    public function toJSON() : string
    {
        return json_encode($this->toObject());
    }

    /** @ORM\PrePersist */
    public function onBeforePersist()
    {
        $time = Db::getTime() ?? new DateTime();

        return $this->initCreatedAt($time);
    }

    /**
     * @ORM\PreUpdate
     * @param PreUpdateEventArgs $event
     * @return $this|Entity
     */
    public function onBeforeUpdate(PreUpdateEventArgs $event)
    {
        if ($this->getArchivedAt()) {
            return $this;
        }

        $time = Db::getTime() ?? new DateTime();

        return $this->setUpdatedAt($time);
    }

    public function validate()
    {
        $Validator = 'Validators\\' . static::class;
        $vars = get_object_vars($this);
        $fields = static::getFieldNames();
        $validator = new $Validator($vars, $fields);

        return $validator->validate();
    }
}
