<?php

namespace Application\Repository;

use Doctrine\ORM\EntityRepository;
use Application\Entity;
use Doctrine\ORM\Tools\Pagination\Paginator;

class Repository extends EntityRepository
{
    protected $totalCount;

    /**
     * @param array $criteria
     * @param array|null $orderBy
     * @param int|null $limit
     * @param int|null $offset
     * @return array
     */
    public function findByWithTotalCount(array $criteria, array $orderBy = null, $limit = null, $offset = null, array $filter = null)
    {
        $qb = $this->createQueryBuilder('e');
        $qb->select();
        $this->addQBCriteria($qb, $criteria);
        if ($filter) {
            $this->addQBFilter($qb, $filter);
        }
        if (is_array($orderBy)) {
            foreach ($orderBy as $k => $v) {
                if (is_string($k)) {
                    $k = 'e.' . $k;
                    $qb->orderBy($k, $v);
                }
            }
        }
        $sql = $qb->getQuery()->getSQL();
        $query = $qb->getQuery()->setFirstResult((int)$offset);
        if ($limit) {
            $query->setMaxResults((int)$limit);
        }

        $dataPaginator = new Paginator($query, $fetchJoinCollection = true);
        $data = array();
        $this->totalCount = count($dataPaginator);
        foreach ($dataPaginator as $entity) {
            $data[] = $entity;
        }
        return $data;
    }

    public function getTotalCount()
    {
        return $this->totalCount;
    }

    protected function addQBFilter(\Doctrine\ORM\QueryBuilder $qb, array $filter)
    {
        foreach ($filter as $val) {
            $param = $val['db_criteria']['param'];
            $value = $val['db_criteria']['value'];
            $criteria = $val['db_criteria']['criteria'];
            $qb->andWhere($criteria);
            $qb->setParameter($param, $value);
        }
        return $this;
    }

    protected function addQBCriteria(\Doctrine\ORM\QueryBuilder $qb, array $where)
    {
        $f = function (\Doctrine\ORM\QueryBuilder $qb, array $where, $exprArr = array(), $type = 'and') use (&$f) {
            foreach ($where as $k => $v) {
                if (($k === 'or' || $k === 'and') && is_array($v)) {
                    $exprArr = $f($qb, $v, $exprArr, $k);
                } else {
                    if (is_numeric($k)) {
                        $qb->andWhere($v);
                    } else {
                        $param = ':param_e_' . $k . '_' . $type;
                        if (is_numeric($v)) {
                            $exprArr[$type][] = $qb->expr()->eq('e.' . $k, (int)$v);
                        } else if (is_array($v)) {
                            $exprArr[$type][] = $qb->expr()->in('e.' . $k, array_map('intval', $v));
                        } else {
                            $exprArr[$type][] = $qb->expr()->eq('e.' . $k, $param);
                            $qb->setParameter($param, $v);
                        }
                    }
                }
            }
            return $exprArr;
        };

        $andExprArr = $f($qb, $where);

        foreach ($andExprArr as $type => $data) {
            if ($type === 'or') {
                $expr = $qb->expr()->orX();
            } else {
                $expr = $qb->expr()->andX();
            }
            $expr->addMultiple($data);
            $qb->andWhere($expr);
        }
        return $this;
    }

}
