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
class UpdateItemCommand extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'recommendation:update-item';

    /**
     * @var string The console command description.
     */
    protected $description = 'Update a single Item in the recomendation engine';


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
        $this->info('Updating single Item.');
        
        $itemKey = $value = $this->option('item');
        $itemId  = $value = $this->option('id');

        Recommendation::updateItem($itemKey, $itemId);
       
        $this->info('Recomendation Item updated.');
    }

   

    /** 
     * Get the console command options.
     * @return array
     */
    protected function getOptions()
    {   
        return [
            ['item', 'i', InputOption::VALUE_REQUIRED, 'Populate data of item with key', null],
            ['id',   null, InputOption::VALUE_REQUIRED, 'Id in the source database of the item', null],
        ];  
    }  
}
