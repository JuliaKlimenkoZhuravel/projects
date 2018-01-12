<?php

namespace Application\Service;

use  Zend\Permissions\Acl\Acl;
use Application\ApplicationTraits\DoctrineEntityManagerAwareTrait;
use Application\ApplicationTraits\LoggerAwareTrait;
use Zend\Mvc\Application as MvcApplication;

class MacService
{
    use DoctrineEntityManagerAwareTrait;
    use LoggerAwareTrait;

    const EVENT_LOGIN = 'user.login';
    const EVENT_PERMISSION_DENIED = 'user.permission.denied';

    /** @var \Zend\Permissions\Acl\Acl */
    private $acl;

    /** @var  \Zend\Mvc\Application */
    private $application;

    /** @var array */
    private $config = array();

    /** @var  \Application\Entity\User */
    private $loggedInUser;

    public function initialize(array $config, MvcApplication $application)
    {
        $this->config = $config['mac'];
        $this->application = $application;
        $routeMatch = $application->getMvcEvent()->getRouteMatch();

        $acl = new Acl();
        $acl->deny();

        $controller = $routeMatch->getParam('controller');
        $action = $routeMatch->getParam('action');
        $namespace = $routeMatch->getParam('__NAMESPACE__');
        $routeName = $routeMatch->getMatchedRouteName();

        $this->acl = $acl;

        $user = $this->setLoggedInUser();
        $this->loggedInUser = $user;
    }

    /**
     * Check if logged in user  has access to specific resource
     *
     * @param string $resourceCode
     * @return bool
     */
    public function checkAccess($resourceCode, $permissionCode)
    {
        $entityManager = $this->getEntityManager();
        $accessResourceRepository = $entityManager->getRepository('Application\Entity\AccessResource');
        /** @var \Application\Repository\UserAccessResourceRepository $userAccessResourceRepository */
        $userAccessResourceRepository = $entityManager->getRepository('Application\Entity\UserAccessResource');
        $permissionRepository = $entityManager->getRepository('Application\Entity\Permission');

        $accessResource = $accessResourceRepository->findOneBy(array('code' => $resourceCode));
        $permission = $permissionRepository->findOneBy(array('code' => $permissionCode));

        if (!$accessResource) {
            return false;
        }

        $user = $this->getLoggedInUser();

        if (!$user) {
            return false;
        }

        if (!$permission) {
            return false;
        }

        $userAccessResource = $userAccessResourceRepository->getByUser($user, $accessResource, $permission);
        if ($userAccessResource) {
            return true;
        }

        return false;
    }

    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @return \Application\Entity\User|null
     */
    public function getLoggedInUser()
    {
        return $this->loggedInUser;
    }

    /**
     * @return \Application\Entity\User|null
     */
    protected function setLoggedInUser()
    {
        $user = null;
        if (empty($_COOKIE[session_name()])) {
            return $user;
        }
        /** @var \Zend\Authentication\AuthenticationService $authService */
        $authService = $this->application->getServiceManager()->get('zfcuser_auth_service');
        $identity = $authService->getIdentity();
        if (is_object($identity)) {
            return $identity;
        }
        return $user;
    }


}