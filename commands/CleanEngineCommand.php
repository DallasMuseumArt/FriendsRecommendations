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
class CleanEngineCommand extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'recommendation:clean-engine';

    /**
     * @var string The console command description.
     */
    protected $description = 'Delete data from recomendation engine';


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
        $this->info('Cleaning data in Recomendation engine');
        
        $itemKeys = $value = $this->option('items');
        $itemKeys = (count($itemKeys) == 0) ? null : $itemKeys;
        
        $msg = (is_null($itemKeys)) ? 'all recomendation engine data' : 'data for item(s) [ ' . implode(', ', $itemKeys) . ' ]';
        
        if ($this->confirm('Do you wish to delete ' . $msg . '? [yes|no]')) {
            Recommendation::cleanEngine($itemKeys);
            
            $this->info('Data cleaning complete');        	
        }else{
            
            $this->info('No delete action was taken.');
        }

    }

   

    /** 
     * Get the console command options.
     * @return array
     */
    protected function getOptions()
    {   
        return [
            ['items', 'i', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'Clean data of specific items', []],
        ];  
    }  
}
