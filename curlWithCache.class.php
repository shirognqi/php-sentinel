<?php
/*
   全局配置
 */
require('config.php');

/* 统计接口，提供三个方法
	lockState：当前业务是否锁住，锁住的读取redis，没锁住的读取接口；
	successStatic：接口成功的统计接口；
 	failStatic：接口失败的统计接口；
 */
require('differentialStatistics.class.php');

/* 内存接口，提供两个方法，读（read） / 写(write)，
 	当换master的时候，可能存在不可读写的状况，该状态将维持20秒！
 */
require('cacheRedis.class.php');

class curlWithCache{
	public function __construct(){
		//
	}
	public function curl($url, $data=null, $method='POST', $time=1){
		// 原生curl或 socket
	}

	public function curlWC($url, $data=null, $method='POST', $time=1, $service=null){
		if($this->serviceTest($service)){					// 业务名
			$static = new differentialStatistics();
			$redis  = new cacheRedis();
			$key 	= 'disaster' . md5($url.json_encode($data));		// 生成内存键
			try{
				$lockState = $static->lockState($service);		// 判定当前系统状态
				if($lockState){						// 尝试读取redis
					$playerInfo = $redis->read($key);		// 返回
					return $playerInfo;
				}
				$playerInfo = $this->curl($url, $data, $method, $time); // 请求接口
				$static->successStatic($service);			// 统计成功数量
				$redis->write($key, $playerInfo);			// 写redis
				return $playerInfo;					// 返回
			}catch(exception $e){						// 灾难发生！！！！
				$static->failStatic($service);				// 统计失败数量
				$playerInfo = $redis->read($key);			// 尝试读取redis
				return $playerInfo;					// 返回
			}

		}else{
			return $this->curl($url, $data, $method, $time);
		}
	}

	public function serviceTest($service){
		if($service) if(is_string($service)) return true;
		return false;
	}
}
