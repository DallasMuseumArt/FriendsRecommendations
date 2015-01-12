<?php namespace DMA\Recommendations;

use Log;
use Illuminate\Support\ServiceProvider;
use DMA\Recommendations\Classes\RecommendationManager;

class RecommendationServiceProvider extends ServiceProvider
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
        $this->app['recommendations'] = $this->app->share(function($app)
        {
            $recommendationManager = new RecommendationManager;
            $recommendationManager->registerItems([
                '\DMA\Recommendations\Classes\Items\ActivityItem',
                '\DMA\Recommendations\Classes\Items\BadgeItem',
                '\DMA\Recommendations\Classes\Items\UserItem',                    
            ]);

            $recommendationManager->registerBackends([
        		'\DMA\Recommendations\Classes\Backends\ElasticSearchBackend',
        	]);
            
            return $recommendationManager;
        });

        // Create alias Facade to the Notification manager
        $this->createAlias('Recommendation', 'DMA\Recommendations\Facades\Recommendation');

    }

    /**
     * Get the services provided by the provider.
     * @return array
     */
    public function provides()
    {
        return ['recommendations'];

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