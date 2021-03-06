<?php
namespace Shieldfy\Monitors;

use Shieldfy\Jury\Judge;

class InternalsMonitor extends MonitorBase
{
    use Judge;

    protected $name = "internals";
    protected $infected = [];

    /**
     * run the monitor
     */
    public function run()
    {
    	$request = $this->collectors['request'];
        $info = $request->getInfo();
        $params = array_merge($info['get'], $info['post']);

        $this->issue('internals');

    	$this->LFIMonitor($params);

    	$this->SSRFMonitor($params);
    }


    public function LFIMonitor(Array $params = [])
    {
    	
        foreach ($params as $key => $value) {
            $charge = $this->sentence($value,'*','lfi');
            if($charge['score']){
            	$charge['value'] = $value;
                $charge['key'] = $key;
                $this->sendToJail( $this->parseScore($charge['score']), $charge );   
            }
        }
    }

    /**
     * 
     */ 
    public function SSRFMonitor(Array $params = [])
    {

    	foreach ($params as $key => $value) {
            
    		//get the url
    		if(preg_match("/^([a-z]{4,6}:[\/]{2,}|www\.)([^:@]+@)?([a-z0-9\.]+)(:[0-9]+)?(#.*)?/is", $value, $matches)){
    			//its a url , becareful
    			$scheme = (isset($matches[1]))?$matches[1]:'http://';
    			$host = (isset($matches[3]))?$matches[3]:'';
    			$port = (isset($matches[4]))?$matches[4]:'';

    			$score = 0;
    			$rulesIds = [];

    			$result = $this->sentence($scheme,'URL:SCHEME');

    			if($result['score']){
	            	$score += $result['score'];
	            	$rulesIds = array_merge($rulesIds, $result['rulesIds']);
	            }

	            $result = $this->sentence($host,'URL:HOST');
                
    			if($result['score']){
	            	$score += $result['score'];
	            	$rulesIds = array_merge($rulesIds, $result['rulesIds']);
	            }

	            $result = $this->sentence($port,'URL:PORT');
    			if($result['score']){
	            	$score += $result['score'];
	            	$rulesIds = array_merge($rulesIds, $result['rulesIds']);
	            }
                
	            if($score){
	            	$charge = [
	            		'key' =>  $key,
	            		'value' => $value,
	            		'score' => $score,
	            		'rulesIds' => $rulesIds
	            	];
	            	$this->infected  = $charge;
	            	$this->sendToJail( $this->parseScore($score), $charge );   
	            }
    		}
        }
        if(count($this->infected) > 0){
           $this->SSRFDeepMonitor();  
        }
    }


    public function SSRFDeepMonitor()
    {
        $this->listenTo([
            'readfile',
            'file_get_contents',
            'file',
            'fopen',
            'fsockopen',
            'curl_setopt'
        ],[$this,'analyze']);
    }

    public function analyze()
    {
        $arg_list = func_get_args();
        foreach($arg_list as $arg):
            //get the final query
            if(is_string($arg)) $this->deepAnalyze($arg);
        endforeach;
    }

    public function deepAnalyze($arg)
    {
        if (stripos($arg, $this->infected['value']) !== false) {
            $stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS); 
            $code = $this->collectors['code']->pushStack($stack)->collectFromStack($stack);
            $this->sendToJail( 'high', $this->infected, $code );
        }
    }


}
