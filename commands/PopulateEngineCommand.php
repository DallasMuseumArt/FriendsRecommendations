<?php namespace DMA\Recommendations\Commands;

use Recommendation;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;

/**
 * Populate Recommendation engine using all active items.
 *
 * @package DMA\Classes\Commands
 * @author Kristen Arnold, Carlos Arroyo
 */
class PopulateEngineCommand extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'recommendation:populate-engine';

    /**
     * @var string The console command description.
     */
    protected $description = 'Populate active items into the active recomendation engine';


    /**
     * Create a new command instance.
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     * @return void
     */
    public function fire()
    {
        $this->info('Start Populating Recomendation engine. ( Go get yourself a cup of coffee or take a nap. This process could take a long time.');
        
        $itemKeys = $value = $this->option('items');
        $itemKeys = (count($itemKeys) == 0) ? null : $itemKeys;
        
        Recommendation::populateEngine($itemKeys);
       
        $this->info('Recomendation engine data complete');
    }

   

    /** 
     * Get the console command options.
     * @return array
     */
    protected function getOptions()
    {   
        return [
            ['items', 'i', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'Populate data of specific items', []],
        ];  
    }  
}
