<?php

define('account_info_SMTPAUTH_NONE',0);
define('account_info_SMTPAUTH_PLAIN',1);

class account_info extends rcube_plugin
{
	public $task = 'settings';
	public $noajax  = true;
    public $noframe = true;
	private $rcmail;
	private static $data = array();

	public function init()
	{
    	$this->rcmail = rcmail::get_instance();
		$this->add_texts('localization/', true);
		$this->add_hook('storage_init', array($this,'storage_init_hook'));
		$this->add_hook('smtp_connect', array($this,'smtp_connect_hook'));
		$this->add_hook('settings_actions', array($this, 'settings_actions'));
		$this->register_action('plugin.account_info', array($this, 'register'));
		$this->register_action('plugin.account_info-download-ios', array($this, 'download_ios'));
		$this->include_stylesheet($this->local_skin_path() .'/account_info.css');
		$this->load_config('config.inc.php.dist');
        if (file_exists("./plugins/account_info/config.inc.php")) {
            $this->load_config('config.inc.php');
        }
	}
	
	function settings_actions($args)
    {
        $args['actions'][] = array(
            'action' => 'plugin.account_info',
            'class'  => 'account_info',
            'label'  => 'account_info',
            'domain' => 'account_info',
        );

        return $args;
    }   

	public function register()
	{
		$this->register_handler('plugin.body', array($this, 'page'));
		$this->rcmail->output->set_pagetitle($this->gettext('account_info'));
	    $this->rcmail->output->send('plugin');
	    $rcmail = rcmail::get_instance();
	}

	public function page()
	{
		global $table;
		
		$rcmail = rcmail::get_instance();
        $user = $rcmail->user;
        $mail_user = $rcmail->get_user_name();
        $mail_pass = $rcmail->get_user_password();
        $identity = $user->get_identity();
        $storage = $rcmail->get_storage();		
		$quota = $storage->get_quota();
        
        $imap_host_prefix = $rcmail->config->get('default_host');
	    $imap_ssl = substr($imap_host_prefix,0,3);
   		$imap_host = str_replace($imap_host_prefix, $rcmail->user->data['mail_host'], $imap_host_prefix);
		$imap_port = $rcmail->config->get('default_port');	
		$smtp_host_prefix = $rcmail->config->get('smtp_server');
		$smtp_ssl = substr($smtp_host_prefix,0,3);
		$smtp_host = str_replace($smtp_host_prefix, $rcmail->user->data['mail_host'], $smtp_host_prefix);
		$smtp_port = $rcmail->config->get('smtp_port');
        
		if ($quota['total'] == 0) {
			$quota_total = 'Unlimited';
			$quota_used = '0 MB (0%)';
			$quota_free = 'Unlimited';
		} else {
			$quota_total = $rcmail->show_bytes($quota['total'] * 1024);
			$quota_used = $rcmail->show_bytes($quota['used'] * 1024) . ' (' . $quota['percent'] . '%)';
			$quota_free_kb = $quota['total'] - $quota['used'];
			$quota_free = $rcmail->show_bytes($quota_free_kb * 1024);
		}
		
		$date_format = "l dS \of F Y \\a\\t H:i";
		$created = date($date_format, strtotime($user->data['created']));
		$last_login = date($date_format, strtotime($user->data['last_login']));
		$failed_login = date($date_format, strtotime($user->data['failed_login']));

		$table = new html_table(array('cols' => 2, 'cellpadding' => 0, 'cellspacing' => 0, 'class' => 'account_info'));
		
		$table->add('ai_title ai_first', html::label('', rcube::Q($this->gettext('ai_user_info'))));
        $table->add_row();
        
        $table->add('', html::label('', rcube::Q($this->gettext('ai_user_id'))));
        $table->add('', rcube::Q($user->ID));
        
        $table->add('', html::label('', rcube::Q($this->gettext('ai_default_identity'))));
        $table->add('', rcube::Q($identity['name'] . ' <' . $identity['email'] . '>'));
        
        $table->add('', html::label('', rcube::Q($this->gettext('ai_created'))));
        $table->add('', rcube::Q($created));

        $table->add('', html::label('', rcube::Q($this->gettext('ai_last_login'))));
        $table->add('', rcube::Q($last_login));
        
        $table->add('', html::label('', rcube::Q($this->gettext('ai_failed_login'))));
        $table->add('', rcube::Q($failed_login));
        
        $table->add('ai_title', html::label('', rcube::Q($this->gettext('ai_storage_info'))));
        $table->add_row();
        
        $table->add('', html::label('', rcube::Q($this->gettext('ai_total_storage'))));
        $table->add('', rcube::Q($quota_total));

		$table->add('', html::label('', rcube::Q($this->gettext('ai_used_storage'))));
        $table->add('', rcube::Q($quota_used));
        
        $table->add('', html::label('', rcube::Q($this->gettext('ai_free_storage'))));
        $table->add('', rcube::Q($quota_free));
        
        $table->add('ai_title', html::label('', rcube::Q($this->gettext('ai_mail_setup_info'))));
        $table->add_row();
        
        $table->add('', html::label('', rcube::Q($this->gettext('ai_username'))));
        $table->add('', rcube::Q($mail_user));
        
        if ($rcmail->config->get('ai_show_pass') == true) {
	        $table->add('', html::label('', rcube::Q($this->gettext('ai_password'))));
	        $table->add('', '<div class="ai_eye ai_eye_inline"></div> <span class="ai_toggle">' . $mail_pass . '</span>');
        }
                
        $table->add('', html::label('', rcube::Q($this->gettext('ai_imap_server'))));
        $table->add('', rcube::Q($imap_host));
        
        $table->add('', html::label('', rcube::Q($this->gettext('ai_imap_port'))));
        $table->add('', rcube::Q($imap_port));
        
        $table->add('', html::label('', rcube::Q($this->gettext('ai_imap_ssl'))));
        $table->add('ai_uppercase', rcube::Q($imap_ssl));
        
        if ($rcmail->config->get('ai_imap_prefix') != null) {
	        $table->add('', html::label('', rcube::Q($this->gettext('ai_imap_prefix'))));
	        $table->add('', rcube::Q($rcmail->config->get('ai_imap_prefix')));
        }
        
        $table->add('', html::label('', rcube::Q($this->gettext('ai_smtp_server'))));
        $table->add('', rcube::Q($smtp_host));
        
        $table->add('', html::label('', rcube::Q($this->gettext('ai_smtp_port'))));
        $table->add('', rcube::Q($smtp_port));
        
        $table->add('', html::label('', rcube::Q($this->gettext('ai_smtp_ssl'))));
        $table->add('ai_uppercase', rcube::Q($smtp_ssl));
        
        if ($rcmail->config->get('ai_ios_download') == true) {
	        $table->add('ai_title', '');
	        $table->add_row();
        
			$table->add('', html::label('', rcube::Q($this->gettext('ai_download_apple_profile'))));
			$table->add('', '<a href="./?_task=settings&_action=plugin.account_info-download-ios">' . $this->gettext('ai_download_ios') . '</a>');
		}

        $out = html::tag('fieldset', '', $table->show());
		return html::div(array('class' => 'box formcontent'), html::div(array('class' => 'boxtitle'), $this->gettext('account_info')) . html::div(array('class' => 'boxcontent'), $out));
	}

	public function get_config_values()
	{
		$rcmail   = rcmail::get_instance();
		$user = $this->rcmail->user;
		$identities = $user->list_identities();
		$main_user = $this->rcmail->get_user_name();

		foreach ($identities as $identity) {
			if ($main_user == $identity['email']) {
				$this->data['name'] = $identity['name'];	
			}
		}
		if (!$this->data['name']) {
			$this->data['name'] = $main_user;
		}
		$this->data['email'] = $main_user;
		$this->data['organization'] = $rcmail->config->get('product_name');

		if (!is_object($this->rcmail->smtp)) {
            $this->rcmail->smtp_init(true);
        }
        $smtp = $this->rcmail->smtp;

		if (!is_object($this->rcmail->storage)) {
            $this->rcmail->storage_init(true);
        }
		$storage = $this->rcmail->storage;
	}

	public function storage_init_hook($args)
	{
		$this->data['IncomingMailServerHostName'] = $args['host'];
	    $this->data['IncomingMailServerPortNumber'] = $args['port'];
	    $this->data['IncomingMailServerUseSSL'] = (true == $args['ssl']);
	    $this->data['IncomingMailServerUsername'] = $args['user'];
	    $this->data['IncomingPassword'] = $args['password'];
	}

	public function smtp_connect_hook($args)
	{
		$host = $args['smtp_server'];
		$host = str_replace('%h', $this->rcmail->user->data['mail_host'], $host);
		$host = str_replace('%s', $_SERVER['SERVER_NAME'], $host);
		$smtp_user = $this->rcmail->config->get('smtp_user');
		$smtp_user = str_replace('%u', $this->rcmail->get_user_name(), $smtp_user);
		$smtp_pass = $this->rcmail->config->get('smtp_pass');
        $smtp_pass = str_replace('%p', $this->rcmail->get_user_password(), $smtp_pass);
		$ssl = false;

		if (strlen($host)>6) {
			$prefix = substr($host,0,6);
			if (($prefix == 'tls://') || ($prefix == 'ssl://') ){
				$host = substr($host,6);
				$ssl = true;
			}
		}

		$this->data['OutgoingMailServerHostName'] = $host;
		$this->data['OutgoingMailServerPortNumber'] = $args['smtp_port'];
		$this->data['OutgoingMailServerUseSSL'] = $ssl;
		$this->data['OutgoingMailServerUsername'] = $smtp_user;
		$this->data['OutgoingPassword'] = $smtp_pass;

		$this->data['OutgoingMailServerAuthentication'] = account_info_SMTPAUTH_NONE;

		 if (is_null($args['smtp_auth_type']) || trim($args['smtp_auth_type'])=='') {
		 	$user = $this->rcmail->config->get('smtp_user');
		 	if (is_string($user) && strlen(trim($user))>0) {
		 		$this->data['OutgoingMailServerAuthentication'] = account_info_SMTPAUTH_PLAIN;
		 	}
		 } else {
		 	switch (strtoupper($args['smtp_auth_type'])) {
        		case 'PLAIN':
        			$this->data['OutgoingMailServerAuthentication'] = account_info_SMTPAUTH_PLAIN;
            	break;
    		}
		 }
	}

	public function ios()
	{
		$this->get_config_values();
		$text = '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
</plist>';
		$xml = new SimpleXMLElement($text);
		$dict = $xml->addChild('dict');
		$dict->addChild('key','PayloadContent');
		$dict->addChild('array');
		$content = $dict->array->addChild('dict');
		$content->addChild('key','EmailAccountDescription');
		$content->addChild('string',$this->data['organization']);
		$content->addChild('key','EmailAccountName');
		$content->addChild('string',$this->data['name']);
		$content->addChild('key','EmailAccountType');
		$content->addChild('string','EmailTypeIMAP');
		$content->addChild('key','EmailAddress');
		$content->addChild('string',$this->data['email']);
		$content->addChild('key','IncomingMailServerAuthentication');
		$content->addChild('string','EmailAuthPassword');
		$content->addChild('key','IncomingMailServerHostName');
		$content->addChild('string',$this->data['IncomingMailServerHostName']);
		$content->addChild('key','IncomingMailServerIMAPPathPrefix');
		$content->addChild('string','INBOX');
		$content->addChild('key','IncomingMailServerPortNumber');
		$content->addChild('integer',$this->data['IncomingMailServerPortNumber']);
		$content->addChild('key','IncomingMailServerUseSSL');
		$content->addChild($this->data['IncomingMailServerUseSSL'] ? 'true' : 'false');
		$content->addChild('key','IncomingMailServerUsername');
		$content->addChild('string',$this->data['IncomingMailServerUsername']);
		$content->addChild('key','IncomingPassword');
		$content->addChild('string',$this->data['IncomingPassword']);
		$content->addChild('key','OutgoingMailServerAuthentication');
		switch($this->data['OutgoingMailServerAuthentication']) {
        	case account_info_SMTPAUTH_PLAIN:
				$content->addChild('string','EmailAuthPassword');
            break;
        	case account_info_SMTPAUTH_NONE:
				$content->addChild('string','EmailAuthNone');
            break;
    	}
		$content->addChild('key','OutgoingMailServerHostName');
		$content->addChild('string',$this->data['OutgoingMailServerHostName']);
		$content->addChild('key','OutgoingMailServerPortNumber');
		$content->addChild('integer',$this->data['OutgoingMailServerPortNumber']);
		$content->addChild('key','OutgoingMailServerUseSSL');
		$content->addChild($this->data['OutgoingMailServerUseSSL'] ? 'true' : 'false');
		$content->addChild('key','OutgoingMailServerUsername');
		$content->addChild('string',$this->data['OutgoingMailServerUsername']);
		$content->addChild('key','OutgoingPassword');
		$content->addChild('string',$this->data['OutgoingPassword']);
		$content->addChild('key','PayloadDescription');
		$content->addChild('string',$this->data['organization'].' '.$this->gettext('ai_payload_description'));
		$content->addChild('key','PayloadDisplayName');
		$content->addChild('string','IMAP Account ('.$this->data['email'].')');
		$content->addChild('key','PayloadIdentifier');
		$content->addChild('string','profile.'.$this->data['email'].'.e-mail');
		if ($this->data['organization'] != '') {
			$content->addChild('key','PayloadOrganization');
			$content->addChild('string',$this->data['organization']);
		}
		$content->addChild('key','PayloadType');
		$content->addChild('string','com.apple.mail.managed');
		$content->addChild('key','PayloadUUID');
		$content->addChild('string',$this->_randomUUID());
		$content->addChild('key','PayloadVersion');
		$content->addChild('integer','1');
		$content->addChild('key','PreventAppSheet');
		$content->addChild('false');
		$content->addChild('key','PreventMove');
		$content->addChild('false');
		$content->addChild('key','SMIMEEnableEncryptionPerMessageSwitch');
		$content->addChild('false');
		$content->addChild('key','SMIMEEnablePerMessageSwitch');
		$content->addChild('false');
		$content->addChild('key','SMIMEEnabled');
		$content->addChild('true');
		$content->addChild('key','SMIMEEncryptByDefault');
		$content->addChild('false');
		$content->addChild('key','SMIMEEncryptionEnabled');
		$content->addChild('true');
		$content->addChild('key','SMIMESigningEnabled');
		$content->addChild('false');
		$content->addChild('key','SMIMESigningUserOverrideable');
		$content->addChild('true');
		$content->addChild('key','allowMailDrop');
		$content->addChild('true');
		$content->addChild('key','disableMailRecentsSyncing');
		$content->addChild('false');
		$dict->addChild('key','PayloadDescription');
		$dict->addChild('string',$this->data['organization'].' '.$this->gettext('ai_payload_description'));
		$dict->addChild('key','PayloadDisplayName');
		$dict->addChild('string',$this->data['organization'].' '.$this->gettext('ai_payload_description'));
		$dict->addChild('key','PayloadIdentifier');
		$dict->addChild('string','profile.'.$this->data['email']);
		if ($this->data['organization'] != '') {
			$dict->addChild('key','PayloadOrganization');
			$dict->addChild('string',$this->data['organization']);
		}
		$dict->addChild('key','PayloadRemovalDisallowed');
		$dict->addChild('false');
		$dict->addChild('key','PayloadType');
		$dict->addChild('string','Configuration');
		$dict->addChild('key','PayloadUUID');
		$dict->addChild('string',$this->_randomUUID());
		$dict->addChild('key','PayloadVersion');
		$dict->addChild('integer','1');

		$dom = new DOMDocument("1.0");
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput = true;
		$dom->loadXML($xml->asXML());
		return $dom->saveXML();
	}

	public function download_ios()
	{
		$ios_data = $this->ios();
		$temp_dir  = $this->rcmail->config->get('temp_dir');
		$tmpfname  = tempnam($temp_dir, 'iosprovdownload');
		$filename = $this->data['email'].'.mobileconfig';
		@file_put_contents($tmpfname, $ios_data);
	    $this->_send_file($tmpfname, $filename);
	    @unlink($tmpfname);
        exit;
	}

	private function _send_file($tmpfname, $filename)
    {
        $browser = new rcube_browser;

        $this->rcmail->output->nocacheing_headers();

        if ($browser->ie && $browser->ver < 7)
            $filename = rawurlencode(abbreviate_string($filename, 55));
        else if ($browser->ie)
            $filename = rawurlencode($filename);
        else
            $filename = addcslashes($filename, '"');

        header("Content-Type: application/octet-stream");
        if ($browser->ie) {
            header("Content-Type: application/force-download");
        }

        @set_time_limit(0);
        header("Content-Disposition: attachment; filename=\"". $filename ."\"");
        header("Content-length: " . filesize($tmpfname));
        readfile($tmpfname);
    }

    private function _randomUUID()
    {
    	$fortychars = sha1(md5(rand()));
    	$fortychars[8]='-';
    	$fortychars[13]='-';
		$fortychars[18]='-';
		$fortychars[23]='-';
    	return strtoupper(substr($fortychars,0,36));
    }
}