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
 * Profile Menu Hook, integrate_profile_areas, called from Profile.controller.php
 *
 * Used to add menu items to the profile area
 * Adds Connected Accounts to profile menu
 *
 * @param mixed[] $profile_areas
 */
function ipa_extauth(&$profile_areas)
{
	global $user_info;

	// No need to show these profile option to guests, perhaps a view_awards permissions should be added?
	if ($user_info['is_guest'])
		return;

	$profile_areas = elk_array_insert($profile_areas, 'exit_profile', array(
		'extauth' => array(
			'label' => 'Connected Accounts',
			'file' => 'Extauth.controller.php',
			'controller' => 'Extauth_Controller',
			'function' => 'action_profile',
			'sc' => 'post',
			'token' => 'profile-ea%u',
			'password' => true,
			'permission' => array(
				'own' => array('profile_identity_any', 'profile_identity_own'),
				'any' => array('profile_identity_any'),
			),
		),
	), 'after');
}
