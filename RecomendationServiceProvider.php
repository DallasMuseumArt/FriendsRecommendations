<?php namespace DMA\Recomendations;

use Log;
use Illuminate\Support\ServiceProvider;
use DMA\Recomendations\Classes\RecomendationManager;

class RecomendationServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Register the service provider.
     * @return void
     */
    public function register()
    {
        // Register RecomendationManager
        $this->app['recomendations'] = $this->app->share(function($app)
        //$this->app->bind('recomendations', function()
        {
            $recomendationManager = new RecomendationManager;
            $recomendationManager->registerItems([
                '\DMA\Recomendations\Classes\Items\ActivityItem' => [
                    'name' => 'Activities',
                    'description' => 'Recomend activities base on tags and user activity.'
                ],
                '\DMA\Recomendations\Classes\Items\BadgeItem' => [
                    'name' => 'Badges',
                    'description' => 'Recomend badges base on tags and user activity.'
                ],
                '\DMA\Recomendations\Classes\Items\UserItem' => [
                    'name' => 'Users',
                    'description' => ''
                ],                    

            ]);

            $recomendationManager->registerBackends([
        		'\DMA\Recomendations\Classes\Backends\ElasticSearchBackend' => [
            		'name' => 'ElasticSearch engine',
            		'description' => 'Provide recomendations using ElasticSearch as backend.'
        		],
        	]);
            
            return $recomendationManager;
        });

        // Create alias Facade to the Notification manager
        $this->createAlias('Recomendation', 'DMA\Recomendations\Facades\Recomendation');

    }

    /**
     * Get the services provided by the provider.
     * @return array
     */
    public function provides()
    {
        return ['recomendations'];

    }

    /**
     * Helper method to quickly setup class aliases for a service
     *
     * @return void
     */
    protected function createAlias($alias, $class)
    {
    	$loader = \Illuminate\Foundation\AliasLoader::getInstance();
    	$loader->alias($alias, $class);
    }

}