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
 * Retrieve a member's settings based on the provider and uid
 *
 * @param string $provider the provider they're using
 * @param string $uid the provider's unique identifier
 *
 * @return array the member settings
 */
function memberByExtUID($provider, $uid)
{
	$db = database();

	$result = $db->query('', '
		SELECT passwd, mem.id_member, id_group, lngfile, is_activated, email_address, additional_groups, member_name, password_salt
		FROM {db_prefix}members AS mem
		RIGHT JOIN {db_prefix}external_authentications AS ext
		ON mem.id_member = ext.id_member
		WHERE ext.provider = {string:provider}
		AND ext.provider_uid = {string:provider_uid}',
		array(
			'provider' => $provider,
			'provider_uid' => $uid,
		)
	);

	$member_found = $db->fetch_assoc($result);
	$db->free_result($result);

	return $member_found;
}

/**
 * Retrieve a member's connected providers
 *
 * @param int $id_member the member's id
 *
 * @return array list of stored providers
 */
function connectedProviders($id_member)
{
	$db = database();

	$result = $db->query('', '
		SELECT provider
		FROM {db_prefix}external_authentications
		WHERE id_member = {int:id}',
		array(
			'id' => $id_member,
		)
	);

	while ($row = $db->fetch_assoc($result))
	{
		$providers[] = $row['provider'];
	}

	$db->free_result($result);

	return $providers;
}

/**
 * Add an auth to the database
 *
 * @param int $id_member the member's id
 * @param string $provider the provider they're using
 * @param string $uid the provider's unique identifier
 * @param string $username the username from the provider
 */
function addAuth($id_member, $provider, $uid, $username)
{
	$db = database();

	$result = $db->insert('insert', '
		{db_prefix}external_authentications',
		array(
			'id_member' => 'int',
			'provider' => 'string',
			'provider_uid' => 'string',
			'username' => 'string'
		),
		array(
			'id_member' => $id_member,
			'provider' => $provider,
			'provider_uid' => $uid,
			'username' => $username
		),
		array()
	);

	return $db->affected_rows($result);
}

/**
 * Remove an auth from the database
 *
 * @param int $id_member the member's id
 * @param string $provider the provider they're using
 */
function deleteAuth($id_member, $provider)
{
	$db = database();

	$result = $db->query('', '
		DELETE FROM {db_prefix}external_authentications
		WHERE
			provider = {string:provider}
		AND
			id_member = {int:id_member}',
		array(
			'id_member' => $id_member,
			'provider' => $provider,
		)
	);

	// Return the amount of deleted auths, unless an error occured.
	return $result ? $db->affected_rows() : false;
}
