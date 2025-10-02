<? 

require 'include.php';

foreach($m->{$config['sitedb']}->usersgroups->find() as $d){
	$users=[];
	foreach($d->users as $u){
		$users[]=tomongoid($u);
	}
	$m->{$config['sitedb']}->usersgroups->updateOne(['_id'=>$d['_id']],['$set'=>['users'=>$users]]);
}