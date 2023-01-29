<?php

namespace think\swow\resetters;

use think\App;
use think\swow\concerns\ModifyProperty;
use think\swow\contract\ResetterInterface;
use think\swow\Sandbox;

/**
 * Class ResetService
 * @package think\swow\resetters
 */
class ResetService implements ResetterInterface
{
    use ModifyProperty;

    /**
     * "handle" function for resetting app.
     *
     * @param App $app
     * @param Sandbox $sandbox
     */
    public function handle(App $app, Sandbox $sandbox)
    {
        foreach ($sandbox->getServices() as $service) {
            $this->modifyProperty($service, $app);
            if (method_exists($service, 'register')) {
                $service->register();
            }
            if (method_exists($service, 'boot')) {
                $app->invoke([$service, 'boot']);
            }
        }
    }

}
