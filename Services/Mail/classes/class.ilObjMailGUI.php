<?php
/* Copyright (c) 1998-2017 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Services/Object/classes/class.ilObjectGUI.php';

/**
* @author Stefan Meyer <meyer@leifos.com> 
* @author Michael Jansen <mjansen@databay.de> 
* @ilCtrl_Calls ilObjMailGUI: ilPermissionGUI
*/
class ilObjMailGUI extends ilObjectGUI
{
	const SETTINGS_SUB_TAB_ID_GENERAL  = 1;
	const SETTINGS_SUB_TAB_ID_EXTERNAL = 2;

	/**
	 * @var ilTabsGUI
	 */
	protected $tabs;

	/**
	 * @var ilRbacSystem
	 */
	protected $rbacsystem;

	/**
	 * @var ilAccessHandler
	 */
	protected $accessHandler;

	/**
	 * @var ilSetting
	 */
	protected $settings;

	/**
	 * ilObjMailGUI constructor.
	 * @param      $a_data
	 * @param int  $a_id
	 * @param bool $a_call_by_reference
	 */
	public function __construct($a_data, $a_id, $a_call_by_reference)
	{
		global $DIC;

		$this->type = 'mail';
		parent::__construct($a_data, $a_id, $a_call_by_reference, false);

		$this->tabs          = $DIC->tabs();
		$this->rbacsystem    = $DIC->rbac()->system();
		$this->accessHandler = $DIC->access();
		$this->settings      = $DIC['ilSetting'];

		$this->lng->loadLanguageModule('mail');
	}

	/**
	 * @inheritdoc
	 */
	function executeCommand()
	{
		$next_class = $this->ctrl->getNextClass($this);
		$cmd        = $this->ctrl->getCmd();
		$this->prepareOutput();

		switch($next_class)
		{
			case 'ilpermissiongui':
				require_once 'Services/AccessControl/classes/class.ilPermissionGUI.php';
				$perm_gui = new ilPermissionGUI($this);
				$this->ctrl->forwardCommand($perm_gui);
				break;

			case 'ilmailtemplategui':
				if(!$this->rbacsystem->checkAccess('write', $this->object->getRefId()))
				{
					$this->ilias->raiseError($this->lng->txt('msg_no_perm_write'), $this->ilias->error_obj->WARNING);
				}

				require_once 'Services/Mail/classes/class.ilMailTemplateGUI.php';
				$this->ctrl->forwardCommand(new ilMailTemplateGUI());
				break;

			default:
				if(!$cmd)
				{
					$cmd = 'view';
				}
				$cmd .= 'Object';
				$this->$cmd();
				break;
		}

		return true;
	}

	/**
	 * @inheritdoc
	 */
	function getAdminTabs()
	{
		$this->getTabs();
	}

	/**
	 * @inheritdoc
	 */
	protected function getTabs()
	{
		if($this->rbacsystem->checkAccess('visible,read', $this->object->getRefId()))
		{
			$this->tabs->addTarget(
				'settings',
				$this->ctrl->getLinkTarget($this, 'view'), array('view', 'save', '', 'showExternalSettingsForm', 'saveExternalSettingsForm'), '', ''
			);
		}

		if($this->rbacsystem->checkAccess('write', $this->object->getRefId()))
		{
			$this->tabs->addTarget(
				'mail_templates',
				$this->ctrl->getLinkTargetByClass('ilmailtemplategui', 'showTemplates'), '', 'ilmailtemplategui'
			);
		}

		if($this->rbacsystem->checkAccess('edit_permission', $this->object->getRefId()))
		{
			$this->tabs->addTarget(
				'perm_settings',
				$this->ctrl->getLinkTargetByClass(array(get_class($this), 'ilpermissiongui'), 'perm'), array('perm','info','owner'), 'ilpermissiongui'
			);
		}
	}

	/**
	 * @param int $activeSubTab
	 */
	protected function buildSettingsSubTabs($activeSubTab)
	{
		if($this->rbacsystem->checkAccess('edit_permission', $this->object->getRefId()))
		{
			$this->tabs->addSubTab(
				self::SETTINGS_SUB_TAB_ID_GENERAL,
				$this->lng->txt('mail_settings_general_tab'),
				$this->ctrl->getLinkTarget($this, 'view')
			);

			if($this->settings->get('mail_allow_external'))
			{
				$this->tabs->addSubTab(
					self::SETTINGS_SUB_TAB_ID_EXTERNAL,
					$this->lng->txt('mail_settings_external_tab'),
					$this->ctrl->getLinkTarget($this, 'showExternalSettingsForm')
				);
			}

			$this->tabs->activateSubTab($activeSubTab);
		}
	}

	/**
	 * @inheritdoc
	 */
	public function viewObject()
	{
		$this->showGeneralSettingsForm();
	}

	/**
	 * @param ilPropertyFormGUI|null $form
	 */
	protected function showGeneralSettingsForm(ilPropertyFormGUI $form = null)
	{
		if(!$this->accessHandler->checkAccess('write,read', '', $this->object->getRefId()))
		{
			$this->ilias->raiseError($this->lng->txt('msg_no_perm_write'), $this->ilias->error_obj->WARNING);
		}

		$this->buildSettingsSubTabs(self::SETTINGS_SUB_TAB_ID_GENERAL);

		if($form === null)
		{
			$form = $this->getGeneralSettingsForm();
			$this->populateGeneralSettingsForm($form);
		}

		$this->tpl->setContent($form->getHTML());
	}

	/**
	 * @return \ilPropertyFormGUI
	 */
	protected function getGeneralSettingsForm()
	{
		require_once 'Services/Form/classes/class.ilPropertyFormGUI.php';
		$form = new ilPropertyFormGUI();

		$form->setFormAction($this->ctrl->getFormAction($this, 'save'));
		$form->setTitle($this->lng->txt('general_settings'));

		$cb = new ilCheckboxInputGUI($this->lng->txt('mail_allow_external'), 'mail_allow_external');
		$cb->setInfo($this->lng->txt('mail_allow_external_info'));
		$cb->setValue(1);
		$form->addItem($cb);

		require_once 'Services/Mail/classes/class.ilMailOptions.php';
		$options = array(
			IL_MAIL_LOCAL => $this->lng->txt('mail_incoming_local'),
			IL_MAIL_EMAIL => $this->lng->txt('mail_incoming_smtp'),
			IL_MAIL_BOTH  => $this->lng->txt('mail_incoming_both')
		);
		$si = new ilSelectInputGUI($this->lng->txt('mail_incoming'), 'mail_incoming_mail');
		$si->setOptions($options);
		$this->ctrl->setParameterByClass('ilobjuserfoldergui', 'ref_id', USER_FOLDER_ID);
		$si->setInfo(sprintf(
			$this->lng->txt('mail_settings_incoming_type_see_also'),
			$this->ctrl->getLinkTargetByClass('ilobjuserfoldergui', 'settings')
		));
		$this->ctrl->clearParametersByClass('ilobjuserfoldergui');
		$form->addItem($si);

		$ti = new ilNumberInputGUI($this->lng->txt('mail_maxsize_attach'), 'mail_maxsize_attach');
		$ti->setSuffix($this->lng->txt('kb'));
		$ti->setInfo($this->lng->txt('mail_max_size_attachments_total'));
		$ti->setMaxLength(10);
		$ti->setSize(10);
		$form->addItem($ti);

		$mn = new ilFormSectionHeaderGUI();
		$mn->setTitle($this->lng->txt('mail_member_notification'));
		$form->addItem($mn);

		$cron_mail = new ilSelectInputGUI($this->lng->txt('cron_mail_notification'), 'mail_notification');
		$cron_options = array(
			0 => $this->lng->txt('cron_mail_notification_never'),
			1 => $this->lng->txt('cron_mail_notification_cron')
		);
		$cron_mail->setOptions($cron_options);
		$cron_mail->setInfo($this->lng->txt('cron_mail_notification_desc'));
		$form->addItem($cron_mail);

		require_once 'Services/Administration/classes/class.ilAdministrationSettingsFormHandler.php';
		ilAdministrationSettingsFormHandler::addFieldsToForm(
			ilAdministrationSettingsFormHandler::FORM_MAIL,
			$form,
			$this
		);

		$form->addCommandButton('save', $this->lng->txt('save'));

		return $form;
	}

	/**
	 * @param ilPropertyFormGUI $form
	 */
	protected function populateGeneralSettingsForm(ilPropertyFormGUI $form)
	{
		$form->setValuesByArray(array(
			'mail_allow_external'          => $this->settings->get('mail_allow_external'),
			'mail_incoming_mail'           => (int)$this->settings->get('mail_incoming_mail'),
			'mail_maxsize_attach'          => $this->settings->get('mail_maxsize_attach'),
			'mail_notification'            => $this->settings->get('mail_notification')
		));
	}

	/**
	 * @inheritdoc
	 */
	public function saveObject()
	{
		if(!$this->rbacsystem->checkAccess('write', $this->object->getRefId()))
		{
			$this->ilias->raiseError($this->lng->txt('msg_no_perm_write'), $this->ilias->error_obj->WARNING);
		}

		$form = $this->getGeneralSettingsForm();
		if($form->checkInput())
		{
			$this->settings->set('mail_allow_external', (int)$form->getInput('mail_allow_external'));
			$this->settings->set('mail_incoming_mail', (int)$form->getInput('mail_incoming_mail'));
			$this->settings->set('mail_maxsize_attach', $form->getInput('mail_maxsize_attach'));
			$this->settings->set('mail_notification', (int)$form->getInput('mail_notification'));

			ilUtil::sendSuccess($this->lng->txt('saved_successfully'), true);
			$this->ctrl->redirect($this);
		}

		$form->setValuesByPost();
		$this->showGeneralSettingsForm($form);
	}

	/**
	 * @param ilPropertyFormGUI|null $form
	 */
	protected function showExternalSettingsFormObject(ilPropertyFormGUI $form = null)
	{
		if(!$this->accessHandler->checkAccess('write,read', '', $this->object->getRefId()))
		{
			$this->ilias->raiseError($this->lng->txt('msg_no_perm_write'), $this->ilias->error_obj->WARNING);
		}

		$this->buildSettingsSubTabs(self::SETTINGS_SUB_TAB_ID_EXTERNAL);

		if($form === null)
		{
			$form = $this->getExternalSettingsForm();
			$this->populateExternalSettingsForm($form);
		}

		$this->tpl->setContent($form->getHTML());
	}

	/**
	 * @return ilPropertyFormGUI
	 */
	protected function getExternalSettingsForm()
	{
		require_once 'Services/Form/classes/class.ilPropertyFormGUI.php';
		$form = new ilPropertyFormGUI();

		$form->setFormAction($this->ctrl->getFormAction($this, 'saveExternalSettingsForm'));
		$form->setTitle($this->lng->txt('mail_settings_external_frm_head'));

		$smtp = new ilCheckboxInputGUI($this->lng->txt('mail_smtp_status'), 'mail_smtp_status');
		$smtp->setInfo($this->lng->txt('mail_smtp_status_info'));
		$smtp->setValue(1);
		$form->addItem($smtp);

		$host = new ilTextInputGUI($this->lng->txt('mail_smtp_host'), 'mail_smtp_host');
		$host->setInfo($this->lng->txt('mail_smtp_host_info'));
		$host->setRequired(true);
		$smtp->addSubItem($host);

		$port = new ilNumberInputGUI($this->lng->txt('mail_smtp_port'), 'mail_smtp_port');
		$port->setInfo($this->lng->txt('mail_smtp_port_info'));
		$port->allowDecimals(false);
		$port->setMinValue(0);
		$port->setMinValue(0);
		$smtp->addSubItem($port);

		$encryption = new ilSelectInputGUI($this->lng->txt('mail_smtp_encryption'), 'mail_smtp_encryption');
		$encryptionOptions = array();
		$encryption->setOptions($encryptionOptions);
		$smtp->addSubItem($encryption);

		$user = new ilTextInputGUI($this->lng->txt('mail_smtp_user'), 'mail_smtp_user');
		$smtp->addSubItem($user);

		$password = new ilTextInputGUI($this->lng->txt('mail_smtp_password'), 'mail_smtp_password');
		$smtp->addSubItem($password);

		$pre = new ilTextInputGUI($this->lng->txt('mail_subject_prefix'),'mail_subject_prefix');
		$pre->setSize(12);
		$pre->setMaxLength(32);
		$pre->setInfo($this->lng->txt('mail_subject_prefix_info'));
		$form->addItem($pre);

		$send_html = new ilCheckboxInputGUI($this->lng->txt('mail_send_html'), 'mail_send_html');
		$send_html->setInfo($this->lng->txt('mail_send_html_info'));
		$send_html->setValue(1);
		$form->addItem($send_html);

		$ti = new ilTextInputGUI($this->lng->txt('mail_external_sender_noreply'), 'mail_external_sender_noreply');
		$ti->setInfo($this->lng->txt('info_mail_external_sender_noreply'));
		$ti->setMaxLength(255);
		$form->addItem($ti);

		$sh = new ilFormSectionHeaderGUI();
		$sh->setTitle($this->lng->txt('mail_settings_user_frm_head'));
		$form->addItem($sh);

		$system_from_name = new ilTextInputGUI($this->lng->txt('mail_system_from_name'), 'mail_system_from_name');
		$system_from_name->setInfo($this->lng->txt('mail_system_from_name_info'));
		$system_from_name->setMaxLength(255);
		$form->addItem($system_from_name);

		$system_return_path = new ilTextInputGUI($this->lng->txt('mail_system_return_path'), 'mail_system_return_path');
		$system_return_path->setInfo($this->lng->txt('mail_system_return_path_info'));
		$system_return_path->setMaxLength(255);
		$form->addItem($system_return_path);

		$sh = new ilFormSectionHeaderGUI();
		$sh->setTitle($this->lng->txt('mail_settings_system_frm_head'));
		$form->addItem($sh);

		$form->addCommandButton('saveExternalSettingsForm', $this->lng->txt('save'));

		return $form;
	}

	/**
	 * @param ilPropertyFormGUI $form
	 */
	protected function populateExternalSettingsForm(ilPropertyFormGUI $form)
	{
		$form->setValuesByArray(array(
			'mail_subject_prefix'          => $this->settings->get('mail_subject_prefix') ? $this->settings->get('mail_subject_prefix') : '[ILIAS]',
			'mail_send_html'               => (int)$this->settings->get('mail_send_html'),
			'mail_external_sender_noreply' => $this->settings->get('mail_external_sender_noreply'),
			'mail_system_from_name'        => $this->settings->get('mail_system_sender_name'),
			'mail_system_return_path'      => $this->settings->get('mail_system_return_path')
		));
	}

	/**
	 * 
	 */
	protected function saveExternalSettingsFormObject()
	{
		if(!$this->rbacsystem->checkAccess('write', $this->object->getRefId()))
		{
			$this->ilias->raiseError($this->lng->txt('msg_no_perm_write'), $this->ilias->error_obj->WARNING);
		}

		$form = $this->getExternalSettingsForm();
		if($form->checkInput())
		{
			// @todo: If smlt settings is active and a username is set, validate password
			
			$this->settings->set('mail_send_html', $form->getInput('mail_send_html'));
			$this->settings->set('mail_subject_prefix', $form->getInput('mail_subject_prefix'));
			$this->settings->set('mail_external_sender_noreply', $form->getInput('mail_external_sender_noreply'));
			$this->settings->set('mail_system_sender_name', $form->getInput('mail_system_from_name'));
			$this->settings->set('mail_system_return_path', $form->getInput('mail_system_return_path'));

			ilUtil::sendSuccess($this->lng->txt('saved_successfully'), true);
			$this->ctrl->redirect($this, 'showExternalSettingsForm');
		}

		$form->setValuesByPost();
		$this->showExternalSettingsFormObject($form);
	}

	/**
	 * @param string $a_target
	 */
	public static function _goto($a_target)
	{
		global $DIC;

		require_once 'Services/Mail/classes/class.ilMail.php';
		$mail = new ilMail($DIC->user()->getId());

		if($DIC->rbac()->system()->checkAccess('internal_mail', $mail->getMailObjectReferenceId()))
		{
			ilUtil::redirect('ilias.php?baseClass=ilMailGUI');
		}
		else
		{
			if($DIC->access()->checkAccess('read', '', ROOT_FOLDER_ID))
			{
				$_GET['cmd']       = 'frameset';
				$_GET['target']    = '';
				$_GET['ref_id']    = ROOT_FOLDER_ID;
				$_GET['baseClass'] = 'ilRepositoryGUI';
				ilUtil::sendFailure(
					sprintf(
						$DIC->language()->txt('msg_no_perm_read_item'), ilObject::_lookupTitle(ilObject::_lookupObjId($a_target))
					),
					true
				);

				include 'ilias.php';
				exit();
			}
		}

		$DIC['ilErr']->raiseError($DIC->language()->txt('msg_no_perm_read'), $DIC['ilErr']->FATAL);
	}
}