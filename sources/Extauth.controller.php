<?php

/**
 * @package External Authentication
 *
 * @author Antony Derham
 * @copyright 2014 Antony Derham
 *
 * @version 1.0
 */

if (!defined('ELK'))
	die('No access...');

/**
 * ExtAuth_Controller class, deals with authenticating external accounts
 */
class Extauth_Controller extends Action_Controller
{
	/**
	 * Entry point in ExtAuth controller
	 */
	public function action_index()
	{
		require_once(SUBSDIR . '/Action.class.php');

		// Where to go
		$subActions = array(
			'login' => array($this, 'action_extlogin'),
			'deauth' => array($this, 'action_deauth'),
			'register' => array($this, 'action_register'),
		);

		$action = new Action();

		// Default action is to login
		$subAction = $action->initialize($subActions, 'login');

		// Do the action
		$action->dispatch($subAction);
	}

	/**
	 * Attempt to authenticate them with HybridAuth
	 *
	 * What it does:
	 *  - takes the provider ID from GET var and attempts HybridAuth login.
	 */
	public function action_extlogin()
	{
		global $context, $user_settings, $user_info, $modSettings;

		require_once(SUBSDIR . '/Extauth.subs.php');

		// No provider? Not the user's fault. Silently go back to login.
		if (!isset($_GET['provider']))
			redirectexit('action=login');
				
		// Include the HybridAuth external libaray and configuration
		$config = EXTDIR . '/hybridauth/config.php';
		require_once(EXTDIR . '/hybridauth/Hybrid/Auth.php');

		try {
			$hybridauth = new Hybrid_Auth($config);
			
			// If it exists, go ahead and try it
			$provider = $_GET['provider'];
			$adapter = $hybridauth->authenticate($provider);
			$profile = $adapter->getUserProfile();

			$member_found = memberByExtUID($provider, $profile->identifier);
			
			// Test here whether it already exists or not and if so, login the user
			if ($member_found) {
				// Here, stuff happens to log the person in
				$user_settings = $member_found;

				require_once(CONTROLLERDIR . '/Auth.controller.php');

				// Activation required?
				if (!checkActivation())
					return;

				// And then do the login. Bye!
				doLogin();
			}
			elseif (isset($_GET['member']))
			{
				checkSession('get');

				$member = $_GET['member'];

				// Create an authentication
				addAuth($member, $provider, $profile->identifier, $profile->displayName);

				redirectexit('action=profile;area=extauth');
			}
			else
			{
				// Here, send them to a partially-filled registration page
				//$context['prefill']['username'] = $profile->displayName;
				//$context['prefill']['email'] = $profile->email;
				/* I'm not sure about these prefills now, actually */

				$context['provider'] = $provider;
				$_SESSION['extauth_info'] = array(
					'provider' => $provider,
					'uid' => $profile->identifier,
					'name' => $profile->displayName,
				);

				// Check if the administrator has it disabled.
				if (!empty($modSettings['registration_method']) && $modSettings['registration_method'] == '3')
					fatal_lang_error('registration_disabled', false);
				// If this user is an admin - redirect them to the admin registration page.
				if (allowedTo('moderate_forum') && !$user_info['is_guest'])
					redirectexit('action=admin;area=regcenter;sa=register');
				// You are not a guest, so you are a member - and members don't get to register twice! (I have no idea what you're doing here if you're a member, but just to be sure)
				elseif (empty($user_info['is_guest']))
					redirectexit();

				// Is the agreement needed? If so, we're doing a "checkbox agreement" regardless of settings (Why? If the user is coming from an external authentication, they want a quick registration, so let's not mess them about with too many steps. We want one single step and then done.)
				$context['require_agreement'] = !empty($modSettings['requireAgreement']);
				
				// Templating time
				loadLanguage('Login');
				loadTemplate('Extauth');
				$context['sub_template'] = 'registration';

				// If you have to agree to the agreement, it needs to be fetched from the file.
				if ($context['require_agreement'])
				{
					// Have we got a localized one?
					if (file_exists(BOARDDIR . '/agreement.' . $user_info['language'] . '.txt'))
						$context['agreement'] = parse_bbc(file_get_contents(BOARDDIR . '/agreement.' . $user_info['language'] . '.txt'), true, 'agreement_' . $user_info['language']);
					elseif (file_exists(BOARDDIR . '/agreement.txt'))
						$context['agreement'] = parse_bbc(file_get_contents(BOARDDIR . '/agreement.txt'), true, 'agreement');
					else
						$context['agreement'] = '';
		
					// Nothing to show, lets disable registration and inform the admin of this error
					if (empty($context['agreement']))
					{
						// No file found or a blank file, log the error so the admin knows there is a problem!
						log_error($txt['registration_agreement_missing'], 'critical');
						fatal_lang_error('registration_disabled', false);
					}
				}

				createToken('register');

				// Forget custom profile fields here. We want username and email. Nothing more.
				// No need to worry about verification either, since we're coming from an externall;y verified account already
			}
		}
		catch (Exception $e) {  
			// In case we have errors 6 or 7, then we have to use Hybrid_Provider_Adapter::logout() to 
			// let hybridauth forget all about the user so we can try to authenticate again.
	
			// Display the received error,
			// to know more please refer to Exceptions handling section on the userguide
			switch ($e->getCode()) { 
				case 0: echo "Unspecified error."; break;
				case 1: echo "Hybridauth configuration error."; break;
				case 2: echo "Provider not properly configured."; break;
				case 3: redirectexit('action=login'); break; // Unknown or disabled provider
				case 4: echo "Missing provider application credentials."; break;
				case 5: echo "Authentication failed. " 
						  . "The user has canceled the authentication or the provider refused the connection."; 
					   break;
				case 6: echo "User profile request failed. Most likely the user is not connected "
					  . "to the provider and he should to authenticate again."; 
					   $adapter->logout();
					   break;
				case 7: echo "User not connected to the provider."; 
					   $adapter->logout();
					   break;
				case 8: echo "Provider does not support this feature."; break;
			}
		}
	}

	public function action_deauth()
	{
		checkSession('get');

		require_once(SUBSDIR . '/Extauth.subs.php');

		$provider = $_GET['provider'];
		$member = $_GET['member'];

		deleteAuth($member, $provider);

		redirectexit('action=profile;area=extauth');
	}

	public function action_profile()
	{
		global $context, $user_info;

		require_once(SUBSDIR . '/Extauth.subs.php');

		$memID = currentMemberID();

		// Load the template file
		loadTemplate('Extauth');

		// Get a list of enabled providers
		$context['enabled_providers'] = array();
		$config = require_once(EXTDIR . '/hybridauth/config.php');

		$context['connected_providers'] = connectedProviders($memID);

		foreach ($config['providers'] as $name => $provider)
		{
			if ($provider['enabled'])
			{
				$context['enabled_providers'][] = $name;
			}
		}
	}

	public function action_register()
	{
		global $txt, $modSettings, $context, $user_info;

		// Check they are who they should be
		checkSession();
		if (!validateToken('register', 'post', true, false))
			redirectexit(); // NOPE NOPE NOPE

		// You can't register if it's disabled.
		if (!empty($modSettings['registration_method']) && $modSettings['registration_method'] == 3)
			fatal_lang_error('registration_disabled', false);

		// Well, if you don't agree, you can't register.
		if (!empty($modSettings['requireAgreement']) && !isset($_POST['checkbox_agreement']))
			redirectexit(); // TODO: Redirect to provider reg page

		// Make sure they came from *somewhere*, have a session.
		if (!isset($_SESSION['old_url']))
			redirectexit(); // TODO: Redirect to provider reg page

		// Check their provider deatils match up correctly
		if ($_POST['provider'] != $_SESSION['extauth_info']['provider'])
			redirectexit(); // TODO: Redirect to provider reg page

		// Clean up
		foreach ($_POST as $key => $value)
		{
			if (!is_array($_POST[$key]))
				$_POST[$key] = htmltrim__recursive(str_replace(array("\n", "\r"), '', $_POST[$key]));
		}

		// Needed for isReservedName() and registerMember()
		require_once(SUBSDIR . '/Members.subs.php');
		// Needed for generateValidationCode()
		require_once(SUBSDIR . '/Auth.subs.php');

		// Set the options needed for registration.
		$regOptions = array(
			'interface' => 'guest',
			'username' => !empty($_POST['user']) ? $_POST['user'] : '',
			'email' => !empty($_POST['email']) ? $_POST['email'] : '',
			'check_reserved_name' => true,
			'check_password_strength' => true,
			'check_email_ban' => true,
			'send_welcome_email' => !empty($modSettings['send_welcomeEmail']),
			'require' => empty($modSettings['registration_method']) ? 'nothing' : ($modSettings['registration_method'] == 1 ? 'activation' : 'approval'),
		);

		mt_srand(time() + 1277);
		$regOptions['password'] = generateValidationCode();
		$regOptions['password_check'] = $regOptions['password'];

		// Registration needs to know your IP
		$req = request();

		$regOptions['ip'] = $user_info['ip'];
		$regOptions['ip2'] = $req->ban_ip();
		$memberID = registerMember($regOptions, 'register');

		// TODO: CHECK REGISTRATION ERRORS!

		// Do our spam protection now.
		spamProtection('register');

		// Since all is well, we'll go ahead and associate the member's external account
		addAuth($memberID, $_SESSION['extauth_info']['provider'], $_SESSION['extauth_info']['uid'], $_SESSION['extauth_info']['name']);

		// Basic template variable setup.
		if (!empty($modSettings['registration_method']))
		{
			loadTemplate('Register');

			$context += array(
				'page_title' => $txt['register'],
				'title' => $txt['registration_successful'],
				'sub_template' => 'after',
				'description' => $modSettings['registration_method'] == 2 ? $txt['approval_after_registration'] : $txt['activate_after_registration']
			);
		}
		else
		{
			call_integration_hook('integrate_activate', array($regOptions['username']));

			setLoginCookie(60 * $modSettings['cookieTime'], $memberID, hash('sha256', Util::strtolower($regOptions['username']) . $regOptions['password'] . $regOptions['register_vars']['password_salt']));

			redirectexit('action=auth;sa=check;member=' . $memberID, $context['server']['needs_login_fix']);
		}
	}
}
