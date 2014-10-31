<?php

/**
 * @package External Authentication
 *
 * @author Antony Derham
 * @copyright 2014 Antony Derham
 *
 * @version 1.0
 */
 
function template_action_profile()
{
	global $context;

	echo '
		<h2>Connected Accounts</h2>
		<p>These are the external accounts connected to your account here. By connecting an external account, you can use that service to login here.</p>';

	foreach ($context['enabled_providers'] as $provider)
	{
		echo '
			<div class="row">
				<div class="large-12 columns">
					<div class="panel">
						<div class="row">
							<div class="large-8 columns">
								<h4>', $provider, '</h4>
							</div>
							<div class="large-4 columns">';
		if (in_array(strtolower($provider), $context['connected_providers']))
		{
			echo '<a class="button alert expand" href="', $scripturl, '?action=extauth;provider=', strtolower($provider), ';sa=deauth;member=', $context['member']['id'], ';', $context['session_var'], '=', $context['session_id'], '" style="margin-bottom: 0;">Disconnect</a>'; //TODO: Store username for each service to display here
		}
		else
		{
			echo '<a class="button login-button-', strtolower($provider), ' expand" href="', $scripturl, '?action=extauth;provider=', strtolower($provider), ';member=', $context['member']['id'], ';', $context['session_var'], '=', $context['session_id'], '" style="margin-bottom: 0;"><i class="icon-', strtolower($provider), '"></i> Connect with ', $provider, '</a>';
		}
		echo '
							</div>
						</div>
					</div>
				</div>
			</div>';
	}
}

function template_registration()
{
	global $context, $scripturl, $txt;

	echo '
		<form action="', $scripturl, '?action=extauth;sa=register" name="registration" id="registration" method="post" accept-charset="UTF-8">
			<div class="row">
				<h2 class="medium-12 large-8 small-centered columns">
					', /*$txt['login'],*/ 'Register
				</h2>
				<div class="medium-12 large-8 small-centered columns"><div class="panel">
					<div class="row absorb-input-margin-bottom"><div class="medium-6 columns">
						<div class="panel callout"><p>We just need a username and email address for your Xydre account</p></div>';

	// Now just get the basic information - username, password, etc.
	echo '
						<input type="text" name="user" id="user" maxlength="80" value="', $context['prefill']['username'], '" autofocus="autofocus" placeholder="', /*$txt['username'],*/ 'Username" />
						<input type="email" name="email" id="email" value="', $context['prefill']['email'], '" placeholder="', /*$txt['password'],*/ 'Email" />
						<input type="email" name="vemail" id="vemail" placeholder="', /*$txt['password'],*/ 'Verify Email" />';

	echo '
					</div><div class="medium-6 columns">';

	if ($context['require_agreement'])
	{
		echo '
						<div id="agreement_box" style="overflow: auto; height: 9.85rem; border: 1px #CCC solid; background: #FFFFFF; padding: 1.25rem; text-align: justify; font-size: 0.8rem; margin-bottom: 1rem;">
							', $context['agreement'], '
						</div>
						<div style="margin-bottom: 1rem; text-align: center;">
							<input id="checkbox_agreement" name="checkbox_agreement" type="checkbox"', ($context['registration_passed_agreement'] ? ' checked' : ''), ' tabindex="', $context['tabindex']++, '" style="margin: 0;">
							<label for="checkbox_agreement" style="vertical-align: text-top;">', $txt['checkbox_agreement'], '</label>
						</div>';
	}

	echo '
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<input type="hidden" name="', $context['register_token_var'], '" value="', $context['register_token'], '" />
						<input type="hidden" name="provider" value="', $context['provider'], '" />
						<button type="submit" class="button login-button-', strtolower($context['provider']), ' expand"><i class="icon-', strtolower($context['provider']), '"></i> Register using ', ucwords($context['provider']), '</button>
					</div></div>
				</div></div>
			</div>
		</form>';
}
