<?php
/*
PHP variables are limited by signed integers (as of 5.4.4) and thus the maximum filter size using one binary string is 2GB - 1B.
Since this script uses powers of 2, the maximum filter for this script is 1GB.
Currently, MD5 is used as the hashing mechanism but for large data, it's best to explore MurmurHash for targeted implementation.
Copyleft please.

m bits
k hashes
n values
p probability of false positive 

//(1-(1-1/m)^(m*ln(2)))^(m*ln(2)/n)=p
// k = m*ln(2)/n;

*/
class BloomFilter {
	const OPTIMIZE_FOR_MEMORY = 1; // smallest m
	const OPTIMIZE_FOR_HASHES = 2; // smallest k
	public static function createFromProbability($n, $p, $method = 0){
		if ($p <= 0 || $p >= 1) throw new Exception('Invalid false positive rate requested.');
		if ($method){
			
		} else {
			$m = pow(2,ceil(log(-$n*log($p)/pow(log(2),2),2))); //approximate estimator method
			$k = round(log(1/$p,2));
		}
		return new BloomFilter($m,$k);
	}
	public static function union($bf1,$bf2){
		
	}
	public static function intersect($bf1,$bf2){
		
	}
	private $n = 0; // # of entries
	private $m; // # of bits in array
	private $k; // # of hash functions
	private $mask;
	private $m_chunk_size;
	private $bit_array;
	public function __construct($m,$k){
		if ($m < 8) throw new Exception('For practical applications, we restrict the bit array length to at least 8 bits.');
		if ($m & ($m - 1) == 0) throw new Exception('The bit array must be power of 2.');
		$this->m = $m; //number of bits
		$this->k = $k;
		$address_bits = log($m,2);
		$this->mask =(1 << $address_bits) - 1;
		$this->m_chunk_size = ceil($address_bits / 8);
		$this->bit_array = (binary)(str_repeat("\0",($this->m >> 3) -1));
	}
	public function calculateProbability($n = 0){
		return pow(1-pow(1-1/$this->m,$this->k*($n ?: $this->n)),$this->k);
		// return pow(1-exp($this->k*($n ?: $this->n)/$this->m),$this->k); //approximate estimator
	}
	public function calculateCapacity($p){
		return floor($this->m*log(2)/log($p,1-pow(1-1/$this->m,$this->m*log(2))));
	}
	public function getElementCount(){
		return $this->n;
	}
	public function getArraySize(){
		return $this->m;
	}
	public function getHashCount(){
		return $this->k;
	}
	public function getInfo($p = null){
		$units = array('','K','M','G','T','P','E','Z','Y');
		$M = $this->m / 8;
		$magnitude = floor(log($M,1024));
		$unit = $units[$magnitude];
		$M /= pow(1024,$magnitude);
		return 'Allocated m: '.$this->m.' bits ('.$M.' '.$unit.'Bytes)'.PHP_EOL.
			'Allocated k: '.$this->k.PHP_EOL.
			(isset($p) ? 'Capacity ('.$p.'): '.number_format($this->calculateCapacity($p)).PHP_EOL : '');
	}
	public function add($key){
		$hash = md5($key,true);
		while ($this->m_chunk_size * $this->k > strlen($hash)) $hash .= md5($hash);
		for ($index = 0; $index < $this->k; $index++){
			$hash_sub = hexdec(unpack('H*',substr($hash,$index*$this->m_chunk_size,$this->m_chunk_size))[1]) & $this->mask;
			$word = $hash_sub >> 3;
			$this->bit_array[$word] = chr(ord($this->bit_array[$word]) | 1 << ($hash_sub % 8));
		}
		$this->n++;
	}
	public function check($key){
		echo 'Checking '.$key.' ';
		$hash = md5($key,true);
		while ($this->m_chunk_size * $this->k > strlen($hash)) $hash .= md5($hash);
		for ($index = 0; $index < $this->k; $index++){
			$hash_sub = hexdec(unpack('H*',substr($hash,$index*$this->m_chunk_size,$this->m_chunk_size))[1]) & $this->mask;
			if (!(ord($this->bit_array[$hash_sub >> 3]) & (1 << ($hash_sub % 8)))) return false;
		}
		return true;
	}
}
/*
$bf1 = BloomFilter::createFromProbability(1000000000, 0.01);
echo $bf1->getInfo(0.01);
//echo $bf1->calculateProbability(26).PHP_EOL;
$max = 100000;
for ($i = 0; $i < $max; $i+=2) $bf1->add('Test'.$i);
for ($i = $max; $i > $max - 10; $i--) echo $i.' '.$bf1->check('Test'.$i).PHP_EOL;
echo $bf1->calculateProbability().PHP_EOL;
*/
?>