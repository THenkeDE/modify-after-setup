<?php

class Modules_ModifyAfterSetup_CustomButtons extends pm_Hook_CustomButtons
{

	public function getButtons()
	{

		$buttons = [];

		$buttons[] = [
			'place'		=> [ self::PLACE_ADMIN_NAVIGATION ],
			'title'		=> pm_Locale::lmsg('navigation_admin_title'),
			'description'	=> pm_Locale::lmsg('navigation_admin_description'),
			'link'		=> pm_Context::getBaseUrl(),
			'visibility'	=> function(){ return pm_Session::getClient()->isAdmin(); }
		];

		return $buttons;

	}

} 
