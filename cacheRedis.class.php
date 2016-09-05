<?php

require('getRedisInfo.class.php');

class cacheRedis{
	
	public function __construct(){
		$this->getRedisInfo();
	}

	public $redisInfo = false;
	
	public function getRedisInfo(){
		$redisInfo = new getRedisInfo();
		$this->redisInfo = $redisInfo->getInfoAutoUpdata();
	}


	public function changeRedisInfo($key, $content){
		if(array_key_exists($key, $this->redisInfo)){
			$this->redisInfo[$key] = $content;
			$this->redisInfo['time'] = time();
		}
		apcu_store(REDIS_INFO_KEY, $this->redisInfo);	
	}
		
	public function  dealWriteError(){
		if(!apcu_exists(WRITE_ERROR_INC_TIMER)){
			apcu_store(WRITE_ERROR_INC_TIMER, time());
			apcu_store(WRITE_ERROR_INC,1);
		}else{
			$writeErrorTime = apcu_fetch(WRITE_ERROR_INC_TIMER)+REDIS_CONF_UPDATA_TIME;
			if($writeErrorTime<time()){
				$times = apcu_inc(WRITE_ERROR_INC);
				if($times>50) $this->changeRedisInfo('write',false);
			}else{
				apcu_store(WRITE_ERROR_INC,1);
				apcu_store(WRITE_ERROR_INC_TIMER,time());
			}
		}

	}

	public function write($key, $value){
		if(!$this->redisInfo['write']) return false;
		try{
			$redis = new redis();
			$redisState = $redis->connect($this->redisInfo['master']['ip'],$this->redisInfo['master']['port']);
			if(!$redisState){
				$this->dealWriteError();
				return false;
			}
			$redisState = $redis->set($key, $value);
			if($redisState !== true){
				$this->dealWriteError();
				return false;
			}
			return true;
		}catch(Exception $e){
			$this->dealWriteError();
			return false;
		}
	}

	public function dealReadError(){
		if(!apcu_exists(READ_ERROR_INC_TIMER)){
			apcu_store(READ_ERROR_INC_TIMER, time());
			apcu_store(READ_ERROR_INC,1);
		}else{
			$readErrorTime = apcu_fetch(READ_ERROR_INC_TIMER)+REDIS_CONF_UPDATA_TIME;
			if($readErrorTime<time()){
				$times = apcu_inc(READ_ERROR_INC);
				if($times>100) $this->changeRedisInfo('read',false);
			}else{
				apcu_store(READ_ERROR_INC,1);
				apcu_store(READ_ERROR_INC_TIMER,time());
			}
		}
	}

	public function readSlave2($key){
		if(!isset($this->redisInfo['slave2'])) return false;
		if($this->redisInfo['slave2'] === false) return false;
		try{
			$redisState = $this->redis_cli->connect($this->redisInfo['slave2']['ip'],$this->redisInfo['slave2']['port']);
			if(!$redisState){
				return false;
			}
			$redisBack = $this->redis_cli->get($key);
			if($redisBack === false){
				return false;
			}
			return $redisBack;
		}catch(exception $e){
			return false;
		}
	}
	public $redis_cli = null;
	public function read($key){
		if(!$this->redisInfo['read']) return false;
		try{
			$this->redis_cli = new redis();
			$redisState = $this->redis_cli->connect($this->redisInfo['slave1']['ip'],$this->redisInfo['slave1']['port']);
			if(!$redisState){
				$redisState2 = $this->readSlave2($key);
				if($redisState2 !== false)
					return $redisState2;
				$this->dealReadError();
			}
			$redisBack = $this->redis_cli->get($key);
			if($redisBack === false){
				$redisState2 = $this->readSlave2($key);
				if($redisState2 !== false)
					return $redisState2;
				$this->dealReadError();
			}
			return $redisBack;
		}catch(Exception $e){
			$redisState2 = $this->readSlave2($key);
			if($redisState2 !== false)
				return $redisState2;
			$this->dealReadError();
			
		}
	}
}
