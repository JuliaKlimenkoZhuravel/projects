<?php

namespace Application\Service;

use Application\ApplicationTraits\DoctrineEntityManagerAwareTrait;
use Application\ApplicationTraits\LoggerAwareTrait;
use Application\ApplicationTraits\MacAwareTrait;

class UserAccessService
{
    use DoctrineEntityManagerAwareTrait;
    use LoggerAwareTrait;
    use MacAwareTrait;
    
    public function getUsersAccessResources()
    {

        $entityManager = $this->getEntityManager();
        /** @var \Application\Repository\UserRepository $userRepository */
        $userRepository = $entityManager->getRepository('Application\Entity\User');
        $all_users = $userRepository->findBy(array(), array('displayName' => 'asc'));
        /** @var \Application\Repository\UserAccessResourceRepository $userAccessResourceRepository */
        $userAccessResourceRepository = $entityManager->getRepository('Application\Entity\UserAccessResource');
        /** @var \Application\Repository\AccessResourceRepository $AccessResourceRepository */
        $accessResourceRepository = $entityManager->getRepository('Application\Entity\AccessResource');
        /** @var \Application\Repository\AccessResourceRepository $PermissionRepository */
        $permissionRepository = $entityManager->getRepository('Application\Entity\Permission');

        $userResources = array();
        $userPermissions = array();
        foreach ($all_users as $userTmp) {
            $resourcesTmp = $userAccessResourceRepository->findAll(array('user' => $userTmp->getId()));
            $userResources[$userTmp->getId()] = array();

            foreach ($resourcesTmp as $resourceTmp) {
                if ($resourceTmp->getUser()->getId() == $userTmp->getId()) {
                    $userResources[$userTmp->getId()][$resourceTmp->getAccessResource()->getId()] = $resourceTmp;
                    $userPermissions[$userTmp->getId()][$resourceTmp->getAccessResource()->getId()] = array();
                    foreach ($resourceTmp->getPermissions() as $permissionTmp) {
                        $userPermissions[$userTmp->getId()][$resourceTmp->getAccessResource()->getId()][$permissionTmp->getId()] = $permissionTmp;
                    }
                }
            }
        }
        $macService = $this->getMacService();
        return array(
            'users' => $all_users,
            'userResources' => $userResources,
            'userPermissions' => $userPermissions,
            'access_resources' => $accessResourceRepository->findAll(),
            'permissions' => $permissionRepository->findAll(),
            'macService' => $macService,
        );
    }

    public function editPermissions(\Application\Entity\User $user, array $permission_ids)
    {
        
        $entityManager = $this->getEntityManager();
        /** @var \Application\Repository\UserAccessResourceRepository $userAccessResourceRepository */
        $userAccessResourceRepository = $entityManager->getRepository('Application\Entity\UserAccessResource');
        /** @var \Application\Repository\AccessResourceRepository $AccessResourceRepository */
        $accessResourceRepository = $entityManager->getRepository('Application\Entity\AccessResource');
        /** @var \Application\Repository\AccessResourceRepository $PermissionRepository */
        $permissionRepository = $entityManager->getRepository('Application\Entity\Permission');

        if ($user) {
            $resource_titles = array();
            $userAccessResourceRepository->deleteByUser($user);
            foreach ($permission_ids as $access_resource_id => $permission_id) {
                $accessResourceTmp = $accessResourceRepository->findOneById($access_resource_id);
                if (empty($accessResourceTmp)) {
                    continue;
                }
                $permission_titles = array();

                foreach ($permission_id as $permission) {
                    $permissionTmp = $permissionRepository->findOneById($permission);
                    if (empty($permissionTmp)) {
                        continue;
                    }
                    $permission_titles[] = $permissionTmp->getTitle();
                    $resourceTmp = $userAccessResourceRepository->findOneBy(array('user' => $user->getId(), 'access_resource' => $accessResourceTmp->getId()));
                    if (!$resourceTmp) {
                        $resourceTmp = new \Application\Entity\UserAccessResource();
                        $resourceTmp->setUser($user);
                        $resourceTmp->setAccessResource($accessResourceTmp);
                    }
                    $resourceTmp->addPermission($permissionTmp);
                    $resourceTmp->setIsAllowed(1);
                    $entityManager->persist($resourceTmp);
                    $entityManager->flush();
                }
                $title = $accessResourceTmp->getTitle();
                if ($permission_titles) {
                    $resource_titles[] = $title . ' (' . implode(',', $permission_titles) . ')';
                }
            }

            return array('success' => true, 'resources' => $resource_titles);
        }
        return array('success' => false, 'resources' => array());
    }
}