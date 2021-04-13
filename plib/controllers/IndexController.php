<?php

class IndexController extends pm_Controller_Action
{

	private $Meta = null;

	public function init()
	{
		parent::init();
		$this->Meta = $this->loadMetaXml();
		$this->view->pageTitle = $this->lmsg('pagetitle',[
			'extension' => $this->Meta->name,
			'version' => '<small>(v' . $this->Meta->version.'-'.$this->Meta->release.'</small>)',
			'icon' => pm_Context::getBaseUrl() . 'images/new-php-logo.svg' 
		]);
	}

	public function indexAction()
	{
		$this->_forward('config');
	}

	public function configAction()
	{

		$form = new pm_Form_Simple();
		$form->addElement('text', 'basefoldername', [
			'label'		=> $this->lmsg('form_basefoldername_label'),
			'placeholder'	=> $this->lmsg('form_basefoldername_placeholder'),
			'escape'	=> false,
			'value'		=> pm_Settings::get('basefoldername'),
		]);
		$form->addElement('select', 'seperatefolder', [
			'label'		=> $this->lmsg('form_seperatefolder_label'),
			'multiOptions'	=> ['1' => 'yes', '0' => 'no'],
			'value'		=> pm_Settings::get('seperatefolder'),
			'required'	=> true,
		]);

		$form->addControlButtons([
			'sendTitle'	=> $this->lmsg('form_save_config'),
			'cancelHidden'	=> true,
			'hideLegend'	=> true,
			'withSeparator'	=> false
		]);

		if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {
			pm_Settings::set('basefoldername', $form->getValue('basefoldername'));
			pm_Settings::set('seperatefolder', $form->getValue('seperatefolder') );
			$this->_status->addMessage('info', $this->lmsg('form_save_success'));
			$this->_helper->json(['redirect' => pm_Context::getBaseUrl()]);
		}

		$this->view->form = $form;
	}

	public function loadMetaXml()
	{
		$meta = (new pm_ServerFileManager)->fileGetContents(pm_Context::getPlibDir() . 'meta.xml');
		$xml = simplexml_load_string($meta);
		return (object) json_decode(json_encode($xml));
	}

}
