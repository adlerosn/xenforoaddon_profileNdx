<?php

class ProfileNdx_indexer_shared
{
	public static function mysql_escape_mimic_fromPhpDoc($inp)
	{//http://php.net/manual/pt_BR/function.mysql-real-escape-string.php
		return str_replace(array('\\',    "\0",  "\n",  "\r",   "'",   '"', "\x1a"),
						   array('\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'),
						   $inp);
	}
	
	public static function startsWith($haystack, $needle)
	{//http://stackoverflow.com/questions/834303/startswith-and-endswith-functions-in-php
		 $length = strlen($needle);
		 return (substr($haystack, 0, $length) === $needle);
	}
	
	public static function getCustomUserFieldsArray(){
		$dbc=XenForo_Application::get('db'); 
		$q = $dbc->fetchRow("SELECT `data_value`
							 FROM   `xf_data_registry`
							 WHERE  `data_key` = 'languages';");
		unset($dbc);
		$q=$q['data_value'];
		$oq=unserialize($q)[1]['phrase_cache'];
		
		$q=array('username'=>'User name',
				 'gender'=>'Gender',
				 'location'=>'Location',
				 'homepage'=>'Home Page',
				 'occupation'=>'Occupation',
				 'about'=>'About Me',
				 'signature'=>'Signature');
		$k=array_keys($oq);
		for ($i=0;$i<count($k);$i++)
		{
			if(ProfileNdx_indexer_shared::startsWith($k[$i],'user_field_'))
			{
				$str=$k[$i];
				$len=count($str);
				$str=substr($str,11);
				$q[$str]=$oq['user_field_'.$str];
			}
		}
		unset($k);
		unset($oq);
		
		return $q;
		}

	public static function rebuildDbNdx(){
		ProfileNdx_indexer_shared::clearDbNdx();
		ProfileNdx_indexer_shared::updateDbTableUserIndex();
	}

	public static function clearDbNdx(){
		$dbc=XenForo_Application::get('db');
		$dbc->query("DROP TABLE IF EXISTS kiror_index_profile;");
	}

	public static function createEmptyDbNdx(){
		$dbc=XenForo_Application::get('db');
		$dbc->query("CREATE TABLE IF NOT EXISTS kiror_index_profile (
				`id` VARCHAR(255) CHARACTER SET utf8,
				`things` LONGBLOB,
				PRIMARY KEY (`id`)
				);");
	}

	public static function sendToDbNdx($dbc,$id,$cont){
		ProfileNdx_indexer_shared::createEmptyDbNdx();
		$cont = serialize($cont);
		$cont = ProfileNdx_indexer_shared::mysql_escape_mimic_fromPhpDoc($cont, $dbc);
		$dbc->query("INSERT INTO kiror_index_profile VALUES ('".$id."','".$cont."')
					 ON DUPLICATE KEY UPDATE things='".$cont."';");
	}

	public static function recoverFromDbNdx($id){
		$dbc=XenForo_Application::get('db');
		$q = $dbc->fetchRow("SELECT `things`
							 FROM   `kiror_index_profile`
							 WHERE  `id` = '".$id."';");
		unset($dbc);
		$q=$q['things'];
		return unserialize($q);
	}
	
	public static function searchableUsers(){
		$q="SELECT `xf_user`.user_id
		    FROM `xf_user_privacy`
		    INNER JOIN `xf_user`
				ON (`xf_user_privacy`.user_id=`xf_user`.user_id)
		    WHERE
				(`allow_view_profile`='everyone' 
					OR `allow_view_profile`='members')
				AND `is_banned`=0
				AND `user_state`='valid';";
		$dbc=XenForo_Application::get('db');
		$f=$dbc->fetchAll($q);
		unset($dbc);
		$r=array();
		foreach($f as $ff){
			$u=$ff['user_id'];
			$r[$u]=$u;
		}
		return $r;
		
	}

	public static function updateDbTableUserIndex(){
		$cfDic=ProfileNdx_indexer_shared::getCustomUserFieldsArray();
		$dbc=XenForo_Application::get('db');
		$xfuser=$dbc->fetchAll("SELECT `user_id`, `username`, `gender`, `user_state`, `is_banned` FROM `xf_user`");
		$xfuserprofile=$dbc->fetchAll("SELECT `user_id`, `location`, `homepage`, `occupation`, `about`, `signature`, `custom_fields` FROM `xf_user_profile`");
		$xfuserid=$dbc->fetchAll("SELECT `user_id` FROM `xf_user` UNION SELECT `user_id` FROM `xf_user_profile`");
		
		$i=0;
		for($i=0;$i<sizeof($xfuserprofile);$i++){
			$xfuserprofile[$i]['custom_fields']=unserialize($xfuserprofile[$i]['custom_fields']);
		}
		
		$min=9999;
		$max=0;
		for($i=0;$i<sizeof($xfuserid);$i++){
			if ($min>$xfuserid[$i]['user_id']) {$min=$xfuserid[$i]['user_id'];};
			if ($max<$xfuserid[$i]['user_id']) {$max=$xfuserid[$i]['user_id'];};
		}
		
		$users=array();
		for($uid=$min;$uid<=$max;$uid++){
			for($i=0;$i<sizeof($xfuser);$i++){
				if ($xfuser[$i]['user_id']==$uid && $xfuser[$i]['is_banned']==0 && $xfuser[$i]['user_state']=='valid'){
					$users[$uid]['username']=$xfuser[$i]['username'];
					$users[$uid]['gender']=$xfuser[$i]['gender'];
					for($j=0;$j<sizeof($xfuserprofile);$j++){
						if ($xfuserprofile[$j]['user_id']==$uid && $xfuserprofile[$j]){
							$users[$uid]['location']=$xfuserprofile[$j]['location'];
							$users[$uid]['homepage']=$xfuserprofile[$j]['homepage'];
							$users[$uid]['occupation']=$xfuserprofile[$j]['occupation'];
							$users[$uid]['about']=$xfuserprofile[$j]['about'];
							$users[$uid]['signature']=$xfuserprofile[$j]['signature'];
							if(array_key_exists('custom_fields',$xfuserprofile[$j])) {
								if (is_array($xfuserprofile[$j]['custom_fields'])){
									foreach(array_keys($xfuserprofile[$j]['custom_fields']) as $key=>$val){
										$users[$uid][$val]=$xfuserprofile[$j]['custom_fields'][$val];
									}
								}
							}
						}
					}
				}
				else if ($xfuser[$i]['user_id']==$uid){
					$users[$uid]=array();
				}
			}
		}
		
		ProfileNdx_indexer_shared::sendToDbNdx($dbc,'usersProfiles',$users);
		ProfileNdx_indexer_shared::sendToDbNdx($dbc,'fieldLabels',$cfDic);
		ProfileNdx_indexer_shared::sendToDbNdx($dbc,'lastchange',$_SERVER['REQUEST_TIME']);
		ProfileNdx_indexer_shared::sendToDbNdx($dbc,'preprocessedStatistic',array());
	}

	public static function validateString($field, $value, $error)
	{
		ProfileNdx_indexer_shared::sendToDbNdx(XenForo_Application::get('db'),'preprocessedStatistic',array());
		$data = $value->getNewData()['xf_option']['option_value'];
		if($data=='') return true;
		$xfopt = explode(',', $data);
		$xfo=array();
		foreach($xfopt as $k=>$v){
			$xfo[$k]=trim($v);
		}
		$var=$xfo;
		unset($k); unset($v); unset($xfo); unset($xfopt);
		$valids=ProfileNdx_indexer_shared::getCustomUserFieldsArray();
		$error = 'Failed when tried asserting the existance of these fields:';
		$errors= array();
		foreach($var as $test){
			if(!array_key_exists($test,$valids)){
				$errors[]= ' <u><b>'.$test.'</b></u>,';
			}
		}
		foreach($errors as $e){
			$error.=$e;
		}
		$error=rtrim($error, ",");
		if(count($errors)>0)
			throw new XenForo_Exception($error,true);
		else
			return true;
	}
}
