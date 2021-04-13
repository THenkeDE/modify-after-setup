<?php

class Modules_ModifyAfterSetup_EventListener implements EventListener
{

	public function filterActions()
	{
		return [
			'subdomain_create',
			'site_create',
			'domain_create'
		];
	}

	public function handleEvent($objectType, $objectId, $action, $oldValues, $newValues)
	{

		$this->basefoldername = pm_Settings::get('basefoldername');
		$this->seperatefolder = pm_Settings::get('seperatefolder') == '1' ? true : false;

		switch( $action ) {
			case 'subdomain_create' :
			case 'site_create' :
			case 'domain_create' :
				$this->default_create( $objectType, $objectId, $action, $oldValues, $newValues );
				break;
		}
	}

	private function default_create( $objectType, $objectId, $action, $oldValues, $newValues )
	{
		foreach( (pm_Domain::getAllDomains()) as $domainData ) {                       
			if( $domainData->getName() == $newValues['Domain Name'] ){
				$sessionFolder = $domainData->getHomePath() . '/' . $this->basefoldername . ( $this->seperatefolder === TRUE ? '_' . $newValues['Domain Name'] : '');
				$fm = new pm_FileManager( $domainData->getId() );
				if( !is_dir($sessionFolder) ){
					$fm->mkdir($sessionFolder, '1750' );
				}
				$tmpfile = sys_get_temp_dir() . '/' . $newValues['Domain Name'] . '.ini';
				$tmpcontent = 'session.save_path=' . $sessionFolder . '/' . "\n";
				file_put_contents($tmpfile,$tmpcontent);
				$res = pm_ApiCli::call('site', ['--update-php-settings',$newValues['Domain Name'],'-settings',$tmpfile]);
				unlink($tmpfile);
			}
		}
	}
}

return new Modules_ModifyAfterSetup_EventListener();
