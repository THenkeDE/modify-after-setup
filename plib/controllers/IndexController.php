<?php

class IndexController extends pm_Controller_Action
{

	private $Meta = null;

	public function indexAction()
	{
		$this->Meta = $this->loadMetaXml();
		$this->view->pageTitle = $this->lmsg('pagetitle',[
			'extension' => $this->Meta->name,
			'version' => '<small>(v' . $this->Meta->version.'-'.$this->Meta->release.'</small>)',
			'icon' => pm_Context::getBaseUrl() . 'images/new-php-logo.svg' 
		]);
	}

	public function loadMetaXml()
	{
		$meta = (new pm_ServerFileManager)->fileGetContents(pm_Context::getPlibDir() . 'meta.xml');
		$xml = simplexml_load_string($meta);
		return (object) json_decode(json_encode($xml));
	}

}
