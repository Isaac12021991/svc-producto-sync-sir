<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\PlusController;

class Task extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:task';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincronizacion de productos con el SIR';

    public function __construct()
    {
        parent::__construct();
        $this->admin_service = new PlusController();
    }
    /**
     * Execute the console command.
     */
    public function handle()
    {

        $this->admin_service->sync();
        return 0;

       /* $text = date("Y-m-d H:i:s");
        Storage::disk('local')->put('cron.txt', $text); */
        

    }
}
