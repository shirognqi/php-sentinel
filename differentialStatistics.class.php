<?php
// 闭环微分失败统计器

class differentialStatistics{
	public $fixTime 		= 60;			// 尝试修复时间秒
	public $fixAfterTestFailerNumber= 50;			// 尝试修复后首次次进入失败的次数

	public $boxNumberMap = array(
		0 => array(8,9,0,1),
		1 => array(9,0,1,2),
		2 => array(0,1,2,3),
		3 => array(1,2,3,4),
		4 => array(2,3,4,5),
		5 => array(3,4,5,6),
		6 => array(4,5,6,7),
		7 => array(5,6,7,8),
		8 => array(6,7,8,9),
		9 => array(7,8,9,0)
	);
	public $boxNumber = 0;
	
	public function __construct(){
		$this->getBoxNmuber();
	}

	public function getBoxNmuber(){
		$this->boxNumber = floor(time()/10)%10;
	}

	public function successStatic($key){
		$locksState = $this->lockState($key);
		if($locksState){
			$unlockState = $this->unlock($key);
			if(!$unlockState)
		       		return false;
		}
		$this->timeTest($key,'suc');
		$successKey = 'box:'.$key.':success:'.$this->boxNumber;
		if(!apcu_exists($successKey)){
			apcu_add($successKey, 1, 50);
			$cache = 1;
		}else{
			$cache = apcu_inc($successKey);
		}
		if($cache == 1){
			$theBoxNumberMap = $this->boxNumberMap[$this->boxNumber];
			for($i = 0; $i <= 9 ;$i++){
				if(!in_array($i, $theBoxNumberMap)){
					if(apcu_exists('box:'.$key.':success:'.$i)){
						apcu_delete('box:'.$key.':success:'.$i);
					}
				}
			}
		}
		return $cache;
	}

	public function failStatic($key){
		$lockState = $this->lockState($key);
		if($lockState){
			$unlockState = $this->unlock($key);
			if(!$unlockState)
				return false;
		}
		$this->timeTest($key,'fail');
		$this->timeTest($key,'suc');
		$faillerKey = 'box:'.$key.':failer:'.$this->boxNumber;
		if(!apcu_exists($faillerKey)){
			apcu_add($faillerKey, 1, 50);
			$cache = 1;
		}else{
			$cache = apcu_inc($faillerKey);
		}
		$theBoxNumberMap = $this->boxNumberMap[$this->boxNumber];
		if($cache === 1){
			for($i = 0; $i <= 9 ;$i++){
				if(!in_array($i, $theBoxNumberMap))
					apcu_delete('box:'.$key.':failer:'.$i);
			}
		}
		
		$successKey0 = 'box:'.$key.':success:'.$theBoxNumberMap[0];
		$successKey1 = 'box:'.$key.':success:'.$theBoxNumberMap[1];
		$successKey2 = 'box:'.$key.':success:'.$theBoxNumberMap[2];
		
		$sucFetch = array($successKey0, $successKey1, $successKey2);

		$sucCount = apcu_fetch($sucFetch);
		
		$suc = 0;
		
		if(!empty($sucCount))
			$suc = array_sum($sucCount);
		
		$suc = ($suc<$this->fixAfterTestFailerNumber*3)? $this->fixAfterTestFailerNumber*3:$suc;
		
		if(($suc/3)<$cache){
			$this->lock($key);
			return false;
		}
		return $cache;
	}
	

	// 防止时间上的回环命中，要及时清空统计数
	public function timeTest($key,$type){
		$now = time();
		if($type === 'suc'){
			$boxKey = 'box:' . $key . ':success:';
		}else{
			$boxKey = 'box:' . $key . ':failer:';
		}
		$timeKey = 'time:'.$boxKey;
		if(apcu_exists($timeKey)){
			$before = apcu_fetch($timeKey)+11;
			if($before<$now){
				for($i=0; $i<=9; $i++){
					apcu_delete($boxKey.$i);
				}
				if($type === 'suc'){
					$theBoxNumberMap = $this->boxNumberMap[$this->boxNumber];
					$successKey0 = $boxKey.$theBoxNumberMap[0];
					$successKey1 = $boxKey.$theBoxNumberMap[1];
					apcu_store($successKey0, $this->fixAfterTestFailerNumber);
					apcu_store($successKey1, $this->fixAfterTestFailerNumber);
				}
			}
		}
		apcu_store($timeKey,$now);
	}

	public function lock($key){
		$lockKey = 'lock:'.$key;
		apcu_store($lockKey, time());
		for($i=0; $i<=9; $i++){
			apcu_delete('box:'.$key.':success:'.$i);
			apcu_delete('box:'.$key.':failer:'.$i);
		}
	}

	public function unlock($key){
		$lockKey = 'lock:'.$key;
		$cache = apcu_exists($lockKey);
		if($cache){
			$lockTime = apcu_fetch($lockKey)+$this->fixTime;
			$time = time();
			if($time>=$lockTime){
				for($i=0; $i<=9; $i++){
					apcu_delete('box:'.$key.':success:'.$i);
					apcu_delete('box:'.$key.':failer:'.$i);
				}

				$theBoxNumberMap = $this->boxNumberMap[$this->boxNumber];
				$successKey0 = 'box:'.$key.':success:'.$theBoxNumberMap[0];
				$successKey1 = 'box:'.$key.':success:'.$theBoxNumberMap[1];
				
				apcu_store($successKey0, $this->fixAfterTestFailerNumber);
				apcu_store($successKey1, $this->fixAfterTestFailerNumber);
				
				apcu_delete($lockKey);
				return true;
			}
		}
		return false;
	}
	public function lockState($key){
		return apcu_exists('lock:'.$key);
	}
}
$b = new differentialStatistics();

if(isset($_GET['bbb']))
	echo $b->failStatic('test');
if(isset($_GET['aaa']))
	echo $b->successStatic('test');
if(isset($_GET['ccc']))
	echo $b->lockState('test');
