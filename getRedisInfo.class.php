<?php

class getRedisInfo{
	public $redis_conf 	= SENTINER_INFO;
	public $sentinel 	= null;
	public function __construct(){
		//
	}
	function setInfo(){
		if(!$this->sentinel){
			$this->sentinel = new redis();
		}
		$infoController = array();
		$masterController = array();
		$slavesController = array();
		foreach($this->redis_conf as $singleSentinel){
			$sentinel = explode(':',$singleSentinel);
			$ip 	= $sentinel[0];
			$port 	= $sentinel[1];
			try{
				$this->sentinel->connect($ip, $port);
				$masterInfo = $this->sentinel->rawcommand('SENTINEL', 'get-master-addr-by-name', 'mymaster');
				$slaveInfo  = $this->sentinel->rawcommand('SENTINEL', 'slaves', 'mymaster');
				$i = 0;
				$slaveIP 	= false;
				$slavePort 	= false;
				$slaves 	= array();
				if(!isset($masterInfo[0]) || !isset($masterInfo[1]))
					continue;

				foreach($slaveInfo as $singleSlave){
					$slaveInfoCount = count($singleSlave);
					$i 		= 0;
					$slaveIP        = false;
					$slavePort      = false;
					for($i; $i<$slaveInfoCount; $i++){
						if(strtoupper($singleSlave[$i]) == 'IP')
							$slaveIP        = $singleSlave[$i+1];
						if(strtoupper($singleSlave[$i]) == 'PORT')
							$slavePort      = $singleSlave[$i+1];
					}
					if($slaveIP == '127.0.0.1') continue;
					if($slavePort == 6379) continue;
					if($slaveIP and $slavePort){
						$slaves[] = array(
							'ip' => $slaveIP,
							'port'=> $slavePort
						);
					}
				}

				$infoController[] = array(
					'master' => array(
						'ip' 	=> $masterInfo[0],
						'port'	=> $masterInfo[1] 
					),
					'slaves' => $slaves
				);
				$masterController[] = $masterInfo[0].':'.$masterInfo[1];
				$_slavesController = array();
				foreach($slaves as $v){
					$_slavesController[] = $v['ip'].':'.$v['port'];
				}
				$slavesController[] = $_slavesController;
			}catch(Exception $e){
				print_r($e);
			}
		}
		$sentinelCount = count($infoController);
		if($sentinelCount<2) exit();
		$master = false;
		$slave1 = false;
		$slave2 = false;
		$masterStatic = array();
		foreach($masterController as $v){
			if(!array_key_exists($v, $masterStatic)){
				$masterStatic[$v] = 1;			
			}else{
				$masterStatic[$v] = $masterStatic[$v]+1;
			}
		}
		while(count($masterStatic)){
			$masterMax = max($masterStatic);
			foreach($masterStatic as $k=>$v){
				if($v == $masterMax){
					try{
						$masterinfo = explode(':', $k);
						$ping = $this->sentinel->connect($masterinfo[0],$masterinfo[1]);
						if($ping)
							$master = array(
									'ip' => $masterinfo[0],
									'port' => $masterinfo[1]
							);
					}catch(Exception $e){
						$master = false;
					}
					unset($masterStatic[$k]);
					break;
				}
			}
			if($master) break;
		}
		$slaveStatic = array();
		foreach($slavesController as $v){
			foreach($v as $v1){
				if(!array_key_exists($v1, $slaveStatic)){
					$slaveStatic[$v1] = 1;
				}else{
					$slaveStatic[$v1] = $slaveStatic[$v1]+1;
				}
			}
		}
		while(count($slaveStatic)){
			$slaveMax = max($slaveStatic);
			foreach($slaveStatic as $k=>$v){
				if($v == $slaveMax){
					$slave1info = explode(':', $k);
					try{
						$ping = $this->sentinel->connect($slave1info[0],$slave1info[1]);
						if($ping)
							$slave1 = array(
								'ip' => $slave1info[0],
								'port' => $slave1info[1]
							);
					}catch(Exception $e){
						$slave1 = false;
					}
					unset($slaveStatic[$k]);
					break;
				}
			}
			if($slave1) break;
		}
		while(count($slaveStatic)){
			$slaveMax = max($slaveStatic);
			foreach($slaveStatic as $k=>$v){
				if($v == $slaveMax){
					$slave1info = explode(':', $k);
					try{
						$ping = $this->sentinel->connect($slave1info[0],$slave1info[1]);
						if($ping)
							$slave2 = array(
								'ip' => $slave1info[0],
								'port' => $slave1info[1]
							);
					}catch(Exception $e){
						$slave2 = false;
					}
					unset($slaveStatic[$k]);
					break;
				}
			}
			if($slave2) break;
		}
		$write = false;
		$read = false;
		if($master)
			$write = true;
		if($slave1)
			$read = true;

		$ret = array();
		$ret['write'] = $write;
		$ret['read']  = $read;
		$ret['master'] = $master;
		$ret['slave1'] = $slave1;
		$ret['slave2'] = $slave2;
		$ret['time']   = time();
		apcu_store(REDIS_INFO_KEY, $ret);
		return $ret;
		
	}
	
	public function getInfo(){
		if(!apcu_exists(REDIS_INFO_KEY)){
			$this->setInfo();
			if(!apcu_exists(REDIS_INFO_KEY)){
				
				$ret = array(
					'write' => false,
					'read'  => fasle,
					'master'=> false,
					'slave1'=> false,
					'slave2'=> false,
					'time'  => time()
				);
				apcu_store(REDIS_INFO_KEY, $ret);
				return $ret;
			}
		}
		return apcu_fetch(REDIS_INFO_KEY);
	}

	public function getInfoAutoUpdata(){
		$redisInfo  = $this->getInfo();
		$updataTime = $redisInfo['time'] + REDIS_CONF_UPDATA_TIME;
		if($updataTime>time()){
			return $redisInfo;
		}else{
			return $this->setInfo();
		}
	}
}
