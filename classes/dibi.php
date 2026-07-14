<?php
namespace Classes;

use Classes\File\Manager;
use Dibi\Connection;
use Dibi\Exception;
use Dibi\Fluent;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Cache;

class Dibi
{
    const RETURN_INSERTED_ID = 'n';
    const TABLE = null;
    const PRIMARY = null;
    const ALL = '*';
    const DES = 'DESC';
    const ASC = 'ASC';

    public Connection $db;
    public EntityManager $entityManager;

    public function __construct(Connection $db)
    {
        $this->db = $db;
        $config = ORMSetup::createAttributeMetadataConfiguration(['./']);
        $this->entityManager = new EntityManager(DriverManager::getConnection(CONNECT, $config), $config);
    }

    public function getAllByPrimary($id): array
    {
        $query = $this->db->select('*');
        $query->from($this::TABLE);
        $query->where('%n = %i', $this::PRIMARY, $id);

        return $this->getEntities($this::class, $query->fetchAll());
    }

    public function getByPrimary($id)
    {
        $query = $this->db->select('*');
        $query->from($this::TABLE);
        $query->where('%n = %i', $this::PRIMARY, $id);

        return $this->getEntity($this::class, (array) $query->fetch());
    }

    public function getAll(bool $onlyQuery = false, string $associated = ''): array|Fluent
    {
        $query = $this->db->select('*');
        $query->from($this::TABLE);
        if ($onlyQuery)
        {
            return $query;
        } else {
            if ($associated)
            {
                return $this->getEntities($this::class, $query->fetchAssoc($associated));
            } else {
                return $this->getEntities($this::class, $query->fetchAll());
            }
        }
    }

    public function managerInsert(array $data, bool $returnKey = false)
    {
        if ($returnKey)
        {
            try {
                return $this->db->insert($this::TABLE, $data)->execute(self::RETURN_INSERTED_ID);
            } catch (Exception $e) {
                bdump($e);
                return false;
            }
        }  else {
            try {
                $this->db->insert($this::TABLE, $data)->execute();
                return true;
            } catch (Exception $e) {
                bdump($e);
                return false;
            }
        }
    }

    public function managerUpdate(array $data, int $id): bool
    {
        try {
            $this->db->update($this::TABLE, $data)
                ->where('%n = %i', $this::PRIMARY, $id)
                ->execute();
            return true;
        } catch (Exception $e) {
            bdump($e);
            return false;
        }
    }

    public function managerDelete(int $id): bool
    {
        try {
            $this->db->delete($this::TABLE)
                ->where('%n = %i', $this::PRIMARY, $id)
                ->execute();
            return true;
        } catch (Exception $e) {
            bdump($e);
            return false;
        }
    }

    public function getFirst(Fluent $query)
    {
        return $query->fetch() ?: false;
    }

    public function deleteByPrimary($id): bool
    {
        try {
            $this->db->delete($this::TABLE)
                ->where('%n = %i', $this::PRIMARY, $id)
                ->execute();
            return true;
        } catch (Exception $e) {
            bdump($e);
            return false;
        }
    }

    public function save(array $data)
    {
        if (array_key_exists($this::PRIMARY, $data))
        {
            $id = $data[$this::PRIMARY];
            unset($data[$this::PRIMARY]);
            $this->db->update($this::TABLE, $data)
                ->where('%n = %i', $this::PRIMARY, $id)
                ->execute();
        } else {
            $this->db->insert($this::TABLE, $data)->execute();
        }
    }

    public function saveEntity(object $entity): bool
    {
        $id = method_exists($entity, 'getId') ? $entity->getId() : null;
        if (empty($id))
        {
            try {
                $this->entityManager->persist($entity);
                $this->entityManager->flush();
                return true;
            } catch (ORMException $e) {
                bdump($e);
                return false;
            }
        } else {
            $class = get_class($entity);
            $existing = $this->entityManager->getRepository($class)->find($entity->getId());

            if (!$existing)
            {
                bdump("Entity {$class} with ID {$entity->getId()} not found.");
                return false;
            }

            foreach (get_class_methods($entity) as $method)
            {
                if (!str_starts_with($method, 'get') || $method === 'getId')
                {
                    continue;
                }

                $value = $entity->$method();

                if ($value === null)
                {
                    continue;
                }

                $setter = 'set' . substr($method, 3);

                if (method_exists($existing, $setter))
                {
                    $existing->$setter($value);
                }
            }

            try {
                $this->entityManager->flush();
                return true;
            } catch (ORMException $e) {
                bdump($e);
                return false;

            }
        }

    }

    public function getEntities(string $class, array $data)
    {
        $class = $this->getClass($class);
        return array_map(function ($row) use ($class) {
            $entity = $this->entityManager->getRepository($class)->find($row->id);

            if (!$entity)
            {
                $entity = new $class();
                foreach ($row as $key => $value)
                {
                    $setter = 'set' . ucfirst($key);
                    if (method_exists($entity, $setter))
                    {
                        $entity->$setter($value);
                    }
                }
            }

            $extraData = [];
            foreach ($row as $key => $value)
            {
                if (!property_exists($entity, $key) && !in_array($key, $this->entityManager->getClassMetadata($class)->getColumnNames()))
                {
                    $extraData[$key] = $value;
                }
            }

            return new EntityWrapper($entity, $extraData);
        }, $data);
    }

    public function getEntity(string $class, array $data)
    {
        if (empty($data))
        {
            return null;
        }
        $class = $this->getClass($class);
        $entity = $this->entityManager->getRepository($class)->find($data['id']);
        if (!$entity)
        {
            $entity = new $class();
            foreach ($data as $key => $value)
            {
                $setter = 'set' . ucfirst($key);
                if (method_exists($entity, $setter))
                {
                    $entity->$setter($value);
                }
            }
        }
        return $entity;
    }

    public function getClass(string $class) : string
    {
        $exploded = explode('\\', $class);
        return sprintf("%s\%s\%s", $exploded[0], $exploded[1], $exploded[1]);
    }


}