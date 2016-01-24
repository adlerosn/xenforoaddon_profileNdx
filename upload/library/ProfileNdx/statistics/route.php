<?php
class ProfileNdx_statistics_route implements XenForo_Route_Interface
{
    public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
    {
        $controller = 'ProfileNdx_statistics_actions';

        return $router->getRouteMatch($controller, '', '');
    }
}
