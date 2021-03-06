<?php
/**
*
* This file is part of the phpBB Forum Software package.
*
* @copyright (c) phpBB Limited <https://www.phpbb.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
* For full copyright and license information, please see
* the docs/CREDITS.txt file.
*
*/

/**
* @ignore
*/
define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);
include($phpbb_root_path . 'includes/functions_display.' . $phpEx);

// Start session management
$user->session_begin();
$auth->acl($user->data);
$user->setup(array('memberlist', 'groups'));

// Setting a variable to let the style designer know where he is...
$template->assign_var('S_IN_MEMBERLIST', true);

// Grab data
$mode		= request_var('mode', '');
$action		= request_var('action', '');
$user_id	= request_var('u', ANONYMOUS);
$username	= request_var('un', '', true);
$group_id	= request_var('g', 0);
$topic_id	= request_var('t', 0);

// Redirect when old mode is used
if ($mode == 'leaders')
{
	send_status_line(301, 'Moved Permanently');
	redirect(append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=team'));
}

// Check our mode...
if (!in_array($mode, array('', 'group', 'viewprofile', 'email', 'contact', 'searchuser', 'team', 'livesearch')))
{
	trigger_error('NO_MODE');
}

switch ($mode)
{
	case 'email':
	break;

	case 'livesearch':
		if (!$config['allow_live_searches'])
		{
			trigger_error('LIVE_SEARCHES_NOT_ALLOWED');
		}
		// No break

	default:
		// Can this user view profiles/memberlist?
		if (!$auth->acl_gets('u_viewprofile', 'a_user', 'a_useradd', 'a_userdel'))
		{
			if ($user->data['user_id'] != ANONYMOUS)
			{
				trigger_error('NO_VIEW_USERS');
			}

			login_box('', ((isset($user->lang['LOGIN_EXPLAIN_' . strtoupper($mode)])) ? $user->lang['LOGIN_EXPLAIN_' . strtoupper($mode)] : $user->lang['LOGIN_EXPLAIN_MEMBERLIST']));
		}
	break;
}

$start	= request_var('start', 0);
$submit = (isset($_POST['submit'])) ? true : false;

$default_key = 'c';
$sort_key = request_var('sk', $default_key);
$sort_dir = request_var('sd', 'a');

// What do you want to do today? ... oops, I think that line is taken ...
switch ($mode)
{
	case 'team':
		// Display a listing of board admins, moderators
		include($phpbb_root_path . 'includes/functions_user.' . $phpEx);

		$page_title = $user->lang['THE_TEAM'];
		$template_html = 'memberlist_team.html';

		$sql = 'SELECT *
			FROM ' . TEAMPAGE_TABLE . '
			ORDER BY teampage_position ASC';
		$result = $db->sql_query($sql, 3600);
		$teampage_data = $db->sql_fetchrowset($result);
		$db->sql_freeresult($result);

		$sql_ary = array(
			'SELECT'	=> 'g.group_id, g.group_name, g.group_colour, g.group_type, ug.user_id as ug_user_id, t.teampage_id',

			'FROM'		=> array(GROUPS_TABLE => 'g'),

			'LEFT_JOIN'	=> array(
				array(
					'FROM'	=> array(TEAMPAGE_TABLE => 't'),
					'ON'	=> 't.group_id = g.group_id',
				),
				array(
					'FROM'	=> array(USER_GROUP_TABLE => 'ug'),
					'ON'	=> 'ug.group_id = g.group_id AND ug.user_pending = 0 AND ug.user_id = ' . (int) $user->data['user_id'],
				),
			),
		);

		$result = $db->sql_query($db->sql_build_query('SELECT', $sql_ary));

		$group_ids = $groups_ary = array();
		while ($row = $db->sql_fetchrow($result))
		{
			if ($row['group_type'] == GROUP_HIDDEN && !$auth->acl_gets('a_group', 'a_groupadd', 'a_groupdel') && $row['ug_user_id'] != $user->data['user_id'])
			{
				$row['group_name'] = $user->lang['GROUP_UNDISCLOSED'];
				$row['u_group'] = '';
			}
			else
			{
				$row['group_name'] = ($row['group_type'] == GROUP_SPECIAL) ? $user->lang['G_' . $row['group_name']] : $row['group_name'];
				$row['u_group'] = append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=group&amp;g=' . $row['group_id']);
			}

			if ($row['teampage_id'])
			{
				// Only put groups into the array we want to display.
				// We are fetching all groups, to ensure we got all data for default groups.
				$group_ids[] = (int) $row['group_id'];
			}
			$groups_ary[(int) $row['group_id']] = $row;
		}
		$db->sql_freeresult($result);

		$sql_ary = array(
			'SELECT'	=> 'u.user_id, u.group_id as default_group, u.username, u.username_clean, u.user_colour, u.user_rank, u.user_posts, u.user_allow_pm, g.group_id',

			'FROM'		=> array(
				USER_GROUP_TABLE => 'ug',
			),

			'LEFT_JOIN'	=> array(
				array(
					'FROM'	=> array(USERS_TABLE => 'u'),
					'ON'	=> 'ug.user_id = u.user_id AND ug.user_pending = 0',
				),
				array(
					'FROM'	=> array(GROUPS_TABLE => 'g'),
					'ON'	=> 'ug.group_id = g.group_id',
				),
			),

			'WHERE'		=> $db->sql_in_set('g.group_id', $group_ids, false, true),

			'ORDER_BY'	=> 'u.username_clean ASC',
		);

		$result = $db->sql_query($db->sql_build_query('SELECT', $sql_ary));

		$user_ary = $user_ids = $group_users = array();
		while ($row = $db->sql_fetchrow($result))
		{
			$row['forums'] = '';
			$row['forums_ary'] = array();
			$user_ary[(int) $row['user_id']] = $row;
			$user_ids[] = (int) $row['user_id'];
			$group_users[(int) $row['group_id']][] = (int) $row['user_id'];
		}
		$db->sql_freeresult($result);

		$user_ids = array_unique($user_ids);

		if (!empty($user_ids) && $config['teampage_forums'])
		{
			$template->assign_var('S_DISPLAY_MODERATOR_FORUMS', true);
			// Get all moderators
			$perm_ary = $auth->acl_get_list($user_ids, array('m_'), false);

			foreach ($perm_ary as $forum_id => $forum_ary)
			{
				foreach ($forum_ary as $auth_option => $id_ary)
				{
					foreach ($id_ary as $id)
					{
						if (!$forum_id)
						{
							$user_ary[$id]['forums'] = $user->lang['ALL_FORUMS'];
						}
						else
						{
							$user_ary[$id]['forums_ary'][] = $forum_id;
						}
					}
				}
			}

			$sql = 'SELECT forum_id, forum_name
				FROM ' . FORUMS_TABLE;
			$result = $db->sql_query($sql);

			$forums = array();
			while ($row = $db->sql_fetchrow($result))
			{
				$forums[$row['forum_id']] = $row['forum_name'];
			}
			$db->sql_freeresult($result);

			foreach ($user_ary as $user_id => $user_data)
			{
				if (!$user_data['forums'])
				{
					foreach ($user_data['forums_ary'] as $forum_id)
					{
						$user_ary[$user_id]['forums_options'] = true;
						if (isset($forums[$forum_id]))
						{
							if ($auth->acl_get('f_list', $forum_id))
							{
								$user_ary[$user_id]['forums'] .= '<option value="">' . $forums[$forum_id] . '</option>';
							}
						}
					}
				}
			}
		}

		$parent_team = 0;
		foreach ($teampage_data as $team_data)
		{
			// If this team entry has no group, it's a category
			if (!$team_data['group_id'])
			{
				$template->assign_block_vars('group', array(
					'GROUP_NAME'  => $team_data['teampage_name'],
				));

				$parent_team = (int) $team_data['teampage_id'];
				continue;
			}

			$group_data = $groups_ary[(int) $team_data['group_id']];
			$group_id = (int) $team_data['group_id'];

			if (!$team_data['teampage_parent'])
			{
				// If the group does not have a parent category, we display the groupname as category
				$template->assign_block_vars('group', array(
					'GROUP_NAME'	=> $group_data['group_name'],
					'GROUP_COLOR'	=> $group_data['group_colour'],
					'U_GROUP'		=> $group_data['u_group'],
				));
			}

			// Display group members.
			if (!empty($group_users[$group_id]))
			{
				foreach ($group_users[$group_id] as $user_id)
				{
					if (isset($user_ary[$user_id]))
					{
						$row = $user_ary[$user_id];
						if ($config['teampage_memberships'] == 1 && ($group_id != $groups_ary[$row['default_group']]['group_id']) && $groups_ary[$row['default_group']]['teampage_id'])
						{
							// Display users in their primary group, instead of the first group, when it is displayed on the teampage.
							continue;
						}

						$rank_title = $rank_img = $rank_img_src = '';
						get_user_rank($row['user_rank'], (($row['user_id'] == ANONYMOUS) ? false : $row['user_posts']), $rank_title, $rank_img, $rank_img_src);

						$template->assign_block_vars('group.user', array(
							'USER_ID'		=> $row['user_id'],
							'FORUMS'		=> $row['forums'],
							'FORUM_OPTIONS'	=> (isset($row['forums_options'])) ? true : false,
							'RANK_TITLE'	=> $rank_title,

							'GROUP_NAME'	=> $groups_ary[$row['default_group']]['group_name'],
							'GROUP_COLOR'	=> $groups_ary[$row['default_group']]['group_colour'],
							'U_GROUP'		=> $groups_ary[$row['default_group']]['u_group'],

							'RANK_IMG'		=> $rank_img,
							'RANK_IMG_SRC'	=> $rank_img_src,

							'U_PM'			=> ($config['allow_privmsg'] && $auth->acl_get('u_sendpm') && ($row['user_allow_pm'] || $auth->acl_gets('a_', 'm_') || $auth->acl_getf_global('m_'))) ? append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=pm&amp;mode=compose&amp;u=' . $row['user_id']) : '',

							'USERNAME_FULL'		=> get_username_string('full', $row['user_id'], $row['username'], $row['user_colour']),
							'USERNAME'			=> get_username_string('username', $row['user_id'], $row['username'], $row['user_colour']),
							'USER_COLOR'		=> get_username_string('colour', $row['user_id'], $row['username'], $row['user_colour']),
							'U_VIEW_PROFILE'	=> get_username_string('profile', $row['user_id'], $row['username'], $row['user_colour']),
						));

						if ($config['teampage_memberships'] != 2)
						{
							unset($user_ary[$user_id]);
						}
					}
				}
			}
		}

		$template->assign_vars(array(
			'PM_IMG'		=> $user->img('icon_contact_pm', $user->lang['SEND_PRIVATE_MESSAGE']))
		);
	break;

	case 'contact':

		$page_title = $user->lang['IM_USER'];
		$template_html = 'memberlist_im.html';

		if (!$auth->acl_get('u_sendim'))
		{
			trigger_error('NOT_AUTHORISED');
		}

		$presence_img = '';
		switch ($action)
		{
			case 'jabber':
				$lang = 'JABBER';
				$sql_field = 'user_jabber';
				$s_select = (@extension_loaded('xml') && $config['jab_enable']) ? 'S_SEND_JABBER' : 'S_NO_SEND_JABBER';
				$s_action = append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=contact&amp;action=$action&amp;u=$user_id");
			break;

			default:
				trigger_error('NO_MODE', E_USER_ERROR);
			break;
		}

		// Grab relevant data
		$sql = "SELECT user_id, username, user_email, user_lang, $sql_field
			FROM " . USERS_TABLE . "
			WHERE user_id = $user_id
				AND user_type IN (" . USER_NORMAL . ', ' . USER_FOUNDER . ')';
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		if (!$row)
		{
			trigger_error('NO_USER');
		}
		else if (empty($row[$sql_field]))
		{
			trigger_error('IM_NO_DATA');
		}

		// Post data grab actions
		switch ($action)
		{
			case 'jabber':
				add_form_key('memberlist_messaging');

				if ($submit && @extension_loaded('xml') && $config['jab_enable'])
				{
					if (check_form_key('memberlist_messaging'))
					{

						include_once($phpbb_root_path . 'includes/functions_messenger.' . $phpEx);

						$subject = sprintf($user->lang['IM_JABBER_SUBJECT'], $user->data['username'], $config['server_name']);
						$message = utf8_normalize_nfc(request_var('message', '', true));

						if (empty($message))
						{
							trigger_error('EMPTY_MESSAGE_IM');
						}

						$messenger = new messenger(false);

						$messenger->template('profile_send_im', $row['user_lang']);
						$messenger->subject(htmlspecialchars_decode($subject));

						$messenger->replyto($user->data['user_email']);
						$messenger->set_addresses($row);

						$messenger->assign_vars(array(
							'BOARD_CONTACT'	=> $config['board_contact'],
							'FROM_USERNAME'	=> htmlspecialchars_decode($user->data['username']),
							'TO_USERNAME'	=> htmlspecialchars_decode($row['username']),
							'MESSAGE'		=> htmlspecialchars_decode($message))
						);

						$messenger->send(NOTIFY_IM);

						$s_select = 'S_SENT_JABBER';
					}
					else
					{
						trigger_error('FORM_INVALID');
					}
				}
			break;
		}

		// Send vars to the template
		$template->assign_vars(array(
			'IM_CONTACT'	=> $row[$sql_field],
			'A_IM_CONTACT'	=> addslashes($row[$sql_field]),

			'USERNAME'		=> $row['username'],
			'CONTACT_NAME'	=> $row[$sql_field],
			'SITENAME'		=> $config['sitename'],

			'PRESENCE_IMG'		=> $presence_img,

			'L_SEND_IM_EXPLAIN'	=> $user->lang['IM_' . $lang],
			'L_IM_SENT_JABBER'	=> sprintf($user->lang['IM_SENT_JABBER'], $row['username']),

			$s_select			=> true,
			'S_IM_ACTION'		=> $s_action)
		);

	break;

	case 'viewprofile':
		// Display a profile
		if ($user_id == ANONYMOUS && !$username)
		{
			trigger_error('NO_USER');
		}

		// Get user...
		$sql = 'SELECT *
			FROM ' . USERS_TABLE . '
			WHERE ' . (($username) ? "username_clean = '" . $db->sql_escape(utf8_clean_string($username)) . "'" : "user_id = $user_id");
		$result = $db->sql_query($sql);
		$member = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		if (!$member)
		{
			trigger_error('NO_USER');
		}

		// a_user admins and founder are able to view inactive users and bots to be able to manage them more easily
		// Normal users are able to see at least users having only changed their profile settings but not yet reactivated.
		if (!$auth->acl_get('a_user') && $user->data['user_type'] != USER_FOUNDER)
		{
			if ($member['user_type'] == USER_IGNORE)
			{
				trigger_error('NO_USER');
			}
			else if ($member['user_type'] == USER_INACTIVE && $member['user_inactive_reason'] != INACTIVE_PROFILE)
			{
				trigger_error('NO_USER');
			}
		}

		$user_id = (int) $member['user_id'];

		// Get group memberships
		// Also get visiting user's groups to determine hidden group memberships if necessary.
		$auth_hidden_groups = ($user_id === (int) $user->data['user_id'] || $auth->acl_gets('a_group', 'a_groupadd', 'a_groupdel')) ? true : false;
		$sql_uid_ary = ($auth_hidden_groups) ? array($user_id) : array($user_id, (int) $user->data['user_id']);

		// Do the SQL thang
		$sql = 'SELECT g.group_id, g.group_name, g.group_type, ug.user_id
			FROM ' . GROUPS_TABLE . ' g, ' . USER_GROUP_TABLE . ' ug
			WHERE ' . $db->sql_in_set('ug.user_id', $sql_uid_ary) . '
				AND g.group_id = ug.group_id
				AND ug.user_pending = 0';
		$result = $db->sql_query($sql);

		// Divide data into profile data and current user data
		$profile_groups = $user_groups = array();
		while ($row = $db->sql_fetchrow($result))
		{
			$row['user_id'] = (int) $row['user_id'];
			$row['group_id'] = (int) $row['group_id'];

			if ($row['user_id'] == $user_id)
			{
				$profile_groups[] = $row;
			}
			else
			{
				$user_groups[$row['group_id']] = $row['group_id'];
			}
		}
		$db->sql_freeresult($result);

		// Filter out hidden groups and sort groups by name
		$group_data = $group_sort = array();
		foreach ($profile_groups as $row)
		{
			if ($row['group_type'] == GROUP_SPECIAL)
			{
				// Lookup group name in language dictionary
				if (isset($user->lang['G_' . $row['group_name']]))
				{
					$row['group_name'] = $user->lang['G_' . $row['group_name']];
				}
			}
			else if (!$auth_hidden_groups && $row['group_type'] == GROUP_HIDDEN && !isset($user_groups[$row['group_id']]))
			{
				// Skip over hidden groups the user cannot see
				continue;
			}

			$group_sort[$row['group_id']] = utf8_clean_string($row['group_name']);
			$group_data[$row['group_id']] = $row;
		}
		unset($profile_groups);
		unset($user_groups);
		asort($group_sort);

		$group_options = '';
		foreach ($group_sort as $group_id => $null)
		{
			$row = $group_data[$group_id];

			$group_options .= '<option value="' . $row['group_id'] . '"' . (($row['group_id'] == $member['group_id']) ? ' selected="selected"' : '') . '>' . $row['group_name'] . '</option>';
		}
		unset($group_data);
		unset($group_sort);

		// What colour is the zebra
		$sql = 'SELECT friend, foe
			FROM ' . ZEBRA_TABLE . "
			WHERE zebra_id = $user_id
				AND user_id = {$user->data['user_id']}";

		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		$foe = ($row['foe']) ? true : false;
		$friend = ($row['friend']) ? true : false;
		$db->sql_freeresult($result);

		if ($config['load_onlinetrack'])
		{
			$sql = 'SELECT MAX(session_time) AS session_time, MIN(session_viewonline) AS session_viewonline
				FROM ' . SESSIONS_TABLE . "
				WHERE session_user_id = $user_id";
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);

			$member['session_time'] = (isset($row['session_time'])) ? $row['session_time'] : 0;
			$member['session_viewonline'] = (isset($row['session_viewonline'])) ? $row['session_viewonline'] :	0;
			unset($row);
		}

		if ($config['load_user_activity'])
		{
			display_user_activity($member);
		}

		// Do the relevant calculations
		$memberdays = max(1, round((time() - $member['user_regdate']) / 86400));
		$posts_per_day = $member['user_posts'] / $memberdays;
		$percentage = ($config['num_posts']) ? min(100, ($member['user_posts'] / $config['num_posts']) * 100) : 0;


		if ($member['user_sig'])
		{
			$parse_flags = ($member['user_sig_bbcode_bitfield'] ? OPTION_FLAG_BBCODE : 0) | OPTION_FLAG_SMILIES;
			$member['user_sig'] = generate_text_for_display($member['user_sig'], $member['user_sig_bbcode_uid'], $member['user_sig_bbcode_bitfield'], $parse_flags, true);
		}

		// We need to check if the modules 'zebra' ('friends' & 'foes' mode),  'notes' ('user_notes' mode) and  'warn' ('warn_user' mode) are accessible to decide if we can display appropriate links
		$zebra_enabled = $friends_enabled = $foes_enabled = $user_notes_enabled = $warn_user_enabled = false;

		// Only check if the user is logged in
		if ($user->data['is_registered'])
		{
			if (!class_exists('p_master'))
			{
				include($phpbb_root_path . 'includes/functions_module.' . $phpEx);
			}
			$module = new p_master();

			$module->list_modules('ucp');
			$module->list_modules('mcp');

			$user_notes_enabled = ($module->loaded('mcp_notes', 'user_notes')) ? true : false;
			$warn_user_enabled = ($module->loaded('mcp_warn', 'warn_user')) ? true : false;
			$zebra_enabled = ($module->loaded('ucp_zebra')) ? true : false;
			$friends_enabled = ($module->loaded('ucp_zebra', 'friends')) ? true : false;
			$foes_enabled = ($module->loaded('ucp_zebra', 'foes')) ? true : false;

			unset($module);
		}

		// Custom Profile Fields
		$profile_fields = array();
		if ($config['load_cpf_viewprofile'])
		{
			$cp = $phpbb_container->get('profilefields.manager');
			$profile_fields = $cp->grab_profile_fields_data($user_id);
			$profile_fields = (isset($profile_fields[$user_id])) ? $cp->generate_profile_fields_template_data($profile_fields[$user_id]) : array();
		}

		/**
		* Modify user data before we display the profile
		*
		* @event core.memberlist_view_profile
		* @var	array	member					Array with user's data
		* @var	bool	user_notes_enabled		Is the mcp user notes module enabled?
		* @var	bool	warn_user_enabled		Is the mcp warnings module enabled?
		* @var	bool	zebra_enabled			Is the ucp zebra module enabled?
		* @var	bool	friends_enabled			Is the ucp friends module enabled?
		* @var	bool	foes_enabled			Is the ucp foes module enabled?
		* @var	bool    friend					Is the user friend?
		* @var	bool	foe						Is the user foe?
		* @var	array	profile_fields			Array with user's profile field data
		* @since 3.1.0-a1
		* @changed 3.1.0-b2 Added friend and foe status
		* @changed 3.1.0-b3 Added profile fields data
		*/
		$vars = array(
			'member',
			'user_notes_enabled',
			'warn_user_enabled',
			'zebra_enabled',
			'friends_enabled',
			'foes_enabled',
			'friend',
			'foe',
			'profile_fields',
		);
		extract($phpbb_dispatcher->trigger_event('core.memberlist_view_profile', compact($vars)));

		$template->assign_vars(show_profile($member, $user_notes_enabled, $warn_user_enabled));

		// If the user has m_approve permission or a_user permission, then list then display unapproved posts
		if ($auth->acl_getf_global('m_approve') || $auth->acl_get('a_user'))
		{
			$sql = 'SELECT COUNT(post_id) as posts_in_queue
				FROM ' . POSTS_TABLE . '
				WHERE poster_id = ' . $user_id . '
					AND ' . $db->sql_in_set('post_visibility', array(ITEM_UNAPPROVED, ITEM_REAPPROVE));
			$result = $db->sql_query($sql);
			$member['posts_in_queue'] = (int) $db->sql_fetchfield('posts_in_queue');
			$db->sql_freeresult($result);
		}
		else
		{
			$member['posts_in_queue'] = 0;
		}

		$template->assign_vars(array(
			'L_POSTS_IN_QUEUE'	=> $user->lang('NUM_POSTS_IN_QUEUE', $member['posts_in_queue']),

			'POSTS_DAY'			=> $user->lang('POST_DAY', $posts_per_day),
			'POSTS_PCT'			=> $user->lang('POST_PCT', $percentage),

			'SIGNATURE'		=> $member['user_sig'],
			'POSTS_IN_QUEUE'=> $member['posts_in_queue'],

			'PM_IMG'		=> $user->img('icon_contact_pm', $user->lang['SEND_PRIVATE_MESSAGE']),
			'EMAIL_IMG'		=> $user->img('icon_contact_email', $user->lang['EMAIL']),
			'JABBER_IMG'	=> $user->img('icon_contact_jabber', $user->lang['JABBER']),
			'SEARCH_IMG'	=> $user->img('icon_user_search', $user->lang['SEARCH']),

			'S_PROFILE_ACTION'	=> append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=group'),
			'S_GROUP_OPTIONS'	=> $group_options,
			'S_CUSTOM_FIELDS'	=> (isset($profile_fields['row']) && sizeof($profile_fields['row'])) ? true : false,

			'U_USER_ADMIN'			=> ($auth->acl_get('a_user')) ? append_sid("{$phpbb_admin_path}index.$phpEx", 'i=users&amp;mode=overview&amp;u=' . $user_id, true, $user->session_id) : '',
			'U_USER_BAN'			=> ($auth->acl_get('m_ban') && $user_id != $user->data['user_id']) ? append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=ban&amp;mode=user&amp;u=' . $user_id, true, $user->session_id) : '',
			'U_MCP_QUEUE'			=> ($auth->acl_getf_global('m_approve')) ? append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=queue', true, $user->session_id) : '',

			'U_SWITCH_PERMISSIONS'	=> ($auth->acl_get('a_switchperm') && $user->data['user_id'] != $user_id) ? append_sid("{$phpbb_root_path}ucp.$phpEx", "mode=switch_perm&amp;u={$user_id}&amp;hash=" . generate_link_hash('switchperm')) : '',

			'S_USER_NOTES'		=> ($user_notes_enabled) ? true : false,
			'S_WARN_USER'		=> ($warn_user_enabled) ? true : false,
			'S_ZEBRA'			=> ($user->data['user_id'] != $user_id && $user->data['is_registered'] && $zebra_enabled) ? true : false,
			'U_ADD_FRIEND'		=> (!$friend && !$foe && $friends_enabled) ? append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=zebra&amp;add=' . urlencode(htmlspecialchars_decode($member['username']))) : '',
			'U_ADD_FOE'			=> (!$friend && !$foe && $foes_enabled) ? append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=zebra&amp;mode=foes&amp;add=' . urlencode(htmlspecialchars_decode($member['username']))) : '',
			'U_REMOVE_FRIEND'	=> ($friend && $friends_enabled) ? append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=zebra&amp;remove=1&amp;usernames[]=' . $user_id) : '',
			'U_REMOVE_FOE'		=> ($foe && $foes_enabled) ? append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=zebra&amp;remove=1&amp;mode=foes&amp;usernames[]=' . $user_id) : '',

			'U_CANONICAL'	=> generate_board_url() . '/' . append_sid("memberlist.$phpEx", 'mode=viewprofile&amp;u=' . $user_id, true, ''),
		));

		if (!empty($profile_fields['row']))
		{
			$template->assign_vars($profile_fields['row']);
		}

		if (!empty($profile_fields['blockrow']))
		{
			foreach ($profile_fields['blockrow'] as $field_data)
			{
				$template->assign_block_vars('custom_fields', $field_data);
			}
		}

		// Inactive reason/account?
		if ($member['user_type'] == USER_INACTIVE)
		{
			$user->add_lang('acp/common');

			$inactive_reason = $user->lang['INACTIVE_REASON_UNKNOWN'];

			switch ($member['user_inactive_reason'])
			{
				case INACTIVE_REGISTER:
					$inactive_reason = $user->lang['INACTIVE_REASON_REGISTER'];
				break;

				case INACTIVE_PROFILE:
					$inactive_reason = $user->lang['INACTIVE_REASON_PROFILE'];
				break;

				case INACTIVE_MANUAL:
					$inactive_reason = $user->lang['INACTIVE_REASON_MANUAL'];
				break;

				case INACTIVE_REMIND:
					$inactive_reason = $user->lang['INACTIVE_REASON_REMIND'];
				break;
			}

			$template->assign_vars(array(
				'S_USER_INACTIVE'		=> true,
				'USER_INACTIVE_REASON'	=> $inactive_reason)
			);
		}

		// Now generate page title
		$page_title = sprintf($user->lang['VIEWING_PROFILE'], $member['username']);
		$template_html = 'memberlist_view.html';

	break;

	case 'email':

		// Send an email
		$page_title = $user->lang['SEND_EMAIL'];
		$template_html = 'memberlist_email.html';

		add_form_key('memberlist_email');

		if (!$config['email_enable'])
		{
			trigger_error('EMAIL_DISABLED');
		}

		if (!$auth->acl_get('u_sendemail'))
		{
			trigger_error('NO_EMAIL');
		}

		// Are we trying to abuse the facility?
		if (time() - $user->data['user_emailtime'] < $config['flood_interval'])
		{
			trigger_error('FLOOD_EMAIL_LIMIT');
		}

		// Determine action...
		$user_id = request_var('u', 0);
		$topic_id = request_var('t', 0);

		// Send email to user...
		if ($user_id)
		{
			if ($user_id == ANONYMOUS || !$config['board_email_form'])
			{
				trigger_error('NO_EMAIL');
			}

			// Get the appropriate username, etc.
			$sql = 'SELECT username, user_email, user_allow_viewemail, user_lang, user_jabber, user_notify_type
				FROM ' . USERS_TABLE . "
				WHERE user_id = $user_id
					AND user_type IN (" . USER_NORMAL . ', ' . USER_FOUNDER . ')';
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);

			if (!$row)
			{
				trigger_error('NO_USER');
			}

			// Can we send email to this user?
			if (!$row['user_allow_viewemail'] && !$auth->acl_get('a_user'))
			{
				trigger_error('NO_EMAIL');
			}
		}
		else if ($topic_id)
		{
			// Send topic heads-up to email address
			$sql = 'SELECT forum_id, topic_title
				FROM ' . TOPICS_TABLE . "
				WHERE topic_id = $topic_id";
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);

			if (!$row)
			{
				trigger_error('NO_TOPIC');
			}

			if ($row['forum_id'])
			{
				if (!$auth->acl_get('f_read', $row['forum_id']))
				{
					trigger_error('SORRY_AUTH_READ');
				}

				if (!$auth->acl_get('f_email', $row['forum_id']))
				{
					trigger_error('NO_EMAIL');
				}
			}
			else
			{
				// If global announcement, we need to check if the user is able to at least read and email in one forum...
				if (!$auth->acl_getf_global('f_read'))
				{
					trigger_error('SORRY_AUTH_READ');
				}

				if (!$auth->acl_getf_global('f_email'))
				{
					trigger_error('NO_EMAIL');
				}
			}
		}
		else
		{
			trigger_error('NO_EMAIL');
		}

		$error = array();

		$name		= utf8_normalize_nfc(request_var('name', '', true));
		$email		= request_var('email', '');
		$email_lang = request_var('lang', $config['default_lang']);
		$subject	= utf8_normalize_nfc(request_var('subject', '', true));
		$message	= utf8_normalize_nfc(request_var('message', '', true));
		$cc			= (isset($_POST['cc_email'])) ? true : false;
		$submit		= (isset($_POST['submit'])) ? true : false;

		if ($submit)
		{
			if (!check_form_key('memberlist_email'))
			{
				$error[] = 'FORM_INVALID';
			}
			if ($user_id)
			{
				if (!$subject)
				{
					$error[] = $user->lang['EMPTY_SUBJECT_EMAIL'];
				}

				if (!$message)
				{
					$error[] = $user->lang['EMPTY_MESSAGE_EMAIL'];
				}

				$name = $row['username'];
				$email_lang = $row['user_lang'];
				$email = $row['user_email'];
			}
			else
			{
				if (!$email || !preg_match('/^' . get_preg_expression('email') . '$/i', $email))
				{
					$error[] = $user->lang['EMPTY_ADDRESS_EMAIL'];
				}

				if (!$name)
				{
					$error[] = $user->lang['EMPTY_NAME_EMAIL'];
				}
			}

			if (!sizeof($error))
			{
				$sql = 'UPDATE ' . USERS_TABLE . '
					SET user_emailtime = ' . time() . '
					WHERE user_id = ' . $user->data['user_id'];
				$result = $db->sql_query($sql);

				include_once($phpbb_root_path . 'includes/functions_messenger.' . $phpEx);
				$messenger = new messenger(false);
				$email_tpl = ($user_id) ? 'profile_send_email' : 'email_notify';

				$mail_to_users = array();

				$mail_to_users[] = array(
					'email_lang'		=> $email_lang,
					'email'				=> $email,
					'name'				=> $name,
					'username'			=> ($user_id) ? $row['username'] : '',
					'to_name'			=> $name,
					'user_jabber'		=> ($user_id) ? $row['user_jabber'] : '',
					'user_notify_type'	=> ($user_id) ? $row['user_notify_type'] : NOTIFY_EMAIL,
					'topic_title'		=> (!$user_id) ? $row['topic_title'] : '',
					'forum_id'			=> (!$user_id) ? $row['forum_id'] : 0,
				);

				// Ok, now the same email if CC specified, but without exposing the users email address
				if ($cc)
				{
					$mail_to_users[] = array(
						'email_lang'		=> $user->data['user_lang'],
						'email'				=> $user->data['user_email'],
						'name'				=> $user->data['username'],
						'username'			=> $user->data['username'],
						'to_name'			=> $name,
						'user_jabber'		=> $user->data['user_jabber'],
						'user_notify_type'	=> ($user_id) ? $user->data['user_notify_type'] : NOTIFY_EMAIL,
						'topic_title'		=> (!$user_id) ? $row['topic_title'] : '',
						'forum_id'			=> (!$user_id) ? $row['forum_id'] : 0,
					);
				}

				foreach ($mail_to_users as $row)
				{
					$messenger->template($email_tpl, $row['email_lang']);
					$messenger->replyto($user->data['user_email']);
					$messenger->to($row['email'], $row['name']);

					if ($user_id)
					{
						$messenger->subject(htmlspecialchars_decode($subject));
						$messenger->im($row['user_jabber'], $row['username']);
						$notify_type = $row['user_notify_type'];
					}
					else
					{
						$notify_type = NOTIFY_EMAIL;
					}

					$messenger->anti_abuse_headers($config, $user);

					$messenger->assign_vars(array(
						'BOARD_CONTACT'	=> $config['board_contact'],
						'TO_USERNAME'	=> htmlspecialchars_decode($row['to_name']),
						'FROM_USERNAME'	=> htmlspecialchars_decode($user->data['username']),
						'MESSAGE'		=> htmlspecialchars_decode($message))
					);

					if ($topic_id)
					{
						$messenger->assign_vars(array(
							'TOPIC_NAME'	=> htmlspecialchars_decode($row['topic_title']),
							'U_TOPIC'		=> generate_board_url() . "/viewtopic.$phpEx?f=" . $row['forum_id'] . "&t=$topic_id")
						);
					}

					$messenger->send($notify_type);
				}

				meta_refresh(3, append_sid("{$phpbb_root_path}index.$phpEx"));
				$message = ($user_id) ? sprintf($user->lang['RETURN_INDEX'], '<a href="' . append_sid("{$phpbb_root_path}index.$phpEx") . '">', '</a>') : sprintf($user->lang['RETURN_TOPIC'],  '<a href="' . append_sid("{$phpbb_root_path}viewtopic.$phpEx", "f={$row['forum_id']}&amp;t=$topic_id") . '">', '</a>');
				trigger_error($user->lang['EMAIL_SENT'] . '<br /><br />' . $message);
			}
		}

		if ($user_id)
		{
			$template->assign_vars(array(
				'S_SEND_USER'		=> true,
				'L_SEND_EMAIL_USER' => $user->lang('SEND_EMAIL_USER', $row['username']),

				'L_EMAIL_BODY_EXPLAIN'	=> $user->lang['EMAIL_BODY_EXPLAIN'],
				'S_POST_ACTION'			=> append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=email&amp;u=' . $user_id))
			);
		}
		else
		{
			$template->assign_vars(array(
				'EMAIL'				=> $email,
				'NAME'				=> $name,
				'S_LANG_OPTIONS'	=> language_select($email_lang),

				'L_EMAIL_BODY_EXPLAIN'	=> $user->lang['EMAIL_TOPIC_EXPLAIN'],
				'S_POST_ACTION'			=> append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=email&amp;t=' . $topic_id))
			);
		}

		$template->assign_vars(array(
			'ERROR_MESSAGE'		=> (sizeof($error)) ? implode('<br />', $error) : '',
			'SUBJECT'			=> $subject,
			'MESSAGE'			=> $message,
			)
		);

	break;

	case 'livesearch':

		$username_chars = $request->variable('username', '', true);

		$sql = 'SELECT username, user_id, user_colour
			FROM ' . USERS_TABLE . '
			WHERE ' . $db->sql_in_set('user_type', array(USER_NORMAL, USER_FOUNDER)) . '
				AND username_clean ' . $db->sql_like_expression(utf8_clean_string($username_chars) . $db->any_char);
		$result = $db->sql_query_limit($sql, 10);
		$user_list = array();

		while ($row = $db->sql_fetchrow($result))
		{
			$user_list[] = array(
				'user_id'		=> (int) $row['user_id'],
				'result'		=> $row['username'],
				'username_full'	=> get_username_string('full', $row['user_id'], $row['username'], $row['user_colour']),
				'display'		=> get_username_string('no_profile', $row['user_id'], $row['username'], $row['user_colour']),
			);
		}
		$db->sql_freeresult($result);
		$json_response = new \phpbb\json_response();
		$json_response->send(array(
			'keyword' => $username_chars,
			'results' => $user_list,
		));

	break;

	case 'group':
	default:
		// The basic memberlist
		$page_title = $user->lang['MEMBERLIST'];
		$template_html = 'memberlist_body.html';
		$pagination = $phpbb_container->get('pagination');

		// Sorting
		$sort_key_text = array('a' => $user->lang['SORT_USERNAME'], 'c' => $user->lang['SORT_JOINED'], 'd' => $user->lang['SORT_POST_COUNT'], 'k' => $user->lang['JABBER']);
		$sort_key_sql = array('a' => 'u.username_clean', 'c' => 'u.user_regdate', 'd' => 'u.user_posts',  'k' => 'u.user_jabber');

		if ($auth->acl_get('a_user'))
		{
			$sort_key_text['e'] = $user->lang['SORT_EMAIL'];
			$sort_key_sql['e'] = 'u.user_email';
		}

		if ($auth->acl_get('u_viewonline'))
		{
			$sort_key_text['l'] = $user->lang['SORT_LAST_ACTIVE'];
			$sort_key_sql['l'] = 'u.user_lastvisit';
		}

		$sort_key_text['m'] = $user->lang['SORT_RANK'];
		$sort_key_sql['m'] = 'u.user_rank';

		$sort_dir_text = array('a' => $user->lang['ASCENDING'], 'd' => $user->lang['DESCENDING']);

		$s_sort_key = '';
		foreach ($sort_key_text as $key => $value)
		{
			$selected = ($sort_key == $key) ? ' selected="selected"' : '';
			$s_sort_key .= '<option value="' . $key . '"' . $selected . '>' . $value . '</option>';
		}

		$s_sort_dir = '';
		foreach ($sort_dir_text as $key => $value)
		{
			$selected = ($sort_dir == $key) ? ' selected="selected"' : '';
			$s_sort_dir .= '<option value="' . $key . '"' . $selected . '>' . $value . '</option>';
		}

		// Additional sorting options for user search ... if search is enabled, if not
		// then only admins can make use of this (for ACP functionality)
		$sql_select = $sql_where_data = $sql_from = $sql_where = $order_by = '';


		$form			= request_var('form', '');
		$field			= request_var('field', '');
		$select_single 	= request_var('select_single', false);

		// Search URL parameters, if any of these are in the URL we do a search
		$search_params = array('username', 'email', 'jabber', 'search_group_id', 'joined_select', 'active_select', 'count_select', 'joined', 'active', 'count', 'ip');

		// We validate form and field here, only id/class allowed
		$form = (!preg_match('/^[a-z0-9_-]+$/i', $form)) ? '' : $form;
		$field = (!preg_match('/^[a-z0-9_-]+$/i', $field)) ? '' : $field;
		if ((($mode == '' || $mode == 'searchuser') || sizeof(array_intersect($request->variable_names(\phpbb\request\request_interface::GET), $search_params)) > 0) && ($config['load_search'] || $auth->acl_get('a_')))
		{
			$username	= request_var('username', '', true);
			$email		= strtolower(request_var('email', ''));
			$jabber		= request_var('jabber', '');
			$search_group_id	= request_var('search_group_id', 0);

			// when using these, make sure that we actually have values defined in $find_key_match
			$joined_select	= request_var('joined_select', 'lt');
			$active_select	= request_var('active_select', 'lt');
			$count_select	= request_var('count_select', 'eq');

			$joined			= explode('-', request_var('joined', ''));
			$active			= explode('-', request_var('active', ''));
			$count			= (request_var('count', '') !== '') ? request_var('count', 0) : '';
			$ipdomain		= request_var('ip', '');

			$find_key_match = array('lt' => '<', 'gt' => '>', 'eq' => '=');

			$find_count = array('lt' => $user->lang['LESS_THAN'], 'eq' => $user->lang['EQUAL_TO'], 'gt' => $user->lang['MORE_THAN']);
			$s_find_count = '';
			foreach ($find_count as $key => $value)
			{
				$selected = ($count_select == $key) ? ' selected="selected"' : '';
				$s_find_count .= '<option value="' . $key . '"' . $selected . '>' . $value . '</option>';
			}

			$find_time = array('lt' => $user->lang['BEFORE'], 'gt' => $user->lang['AFTER']);
			$s_find_join_time = '';
			foreach ($find_time as $key => $value)
			{
				$selected = ($joined_select == $key) ? ' selected="selected"' : '';
				$s_find_join_time .= '<option value="' . $key . '"' . $selected . '>' . $value . '</option>';
			}

			$s_find_active_time = '';
			foreach ($find_time as $key => $value)
			{
				$selected = ($active_select == $key) ? ' selected="selected"' : '';
				$s_find_active_time .= '<option value="' . $key . '"' . $selected . '>' . $value . '</option>';
			}

			$sql_where .= ($username) ? ' AND u.username_clean ' . $db->sql_like_expression(str_replace('*', $db->any_char, utf8_clean_string($username))) : '';
			$sql_where .= ($auth->acl_get('a_user') && $email) ? ' AND u.user_email ' . $db->sql_like_expression(str_replace('*', $db->any_char, $email)) . ' ' : '';
			$sql_where .= ($jabber) ? ' AND u.user_jabber ' . $db->sql_like_expression(str_replace('*', $db->any_char, $jabber)) . ' ' : '';
			$sql_where .= (is_numeric($count) && isset($find_key_match[$count_select])) ? ' AND u.user_posts ' . $find_key_match[$count_select] . ' ' . (int) $count . ' ' : '';

			if (isset($find_key_match[$joined_select]) && sizeof($joined) == 3)
			{
				$joined_time = gmmktime(0, 0, 0, (int) $joined[1], (int) $joined[2], (int) $joined[0]);

				if ($joined_time !== false)
				{
					$sql_where .= " AND u.user_regdate " . $find_key_match[$joined_select] . ' ' . $joined_time;
				}
			}

			if (isset($find_key_match[$active_select]) && sizeof($active) == 3 && $auth->acl_get('u_viewonline'))
			{
				$active_time = gmmktime(0, 0, 0, (int) $active[1], (int) $active[2], (int) $active[0]);

				if ($active_time !== false)
				{
					$sql_where .= " AND u.user_lastvisit " . $find_key_match[$active_select] . ' ' . $active_time;
				}
			}

			$sql_where .= ($search_group_id) ? " AND u.user_id = ug.user_id AND ug.group_id = $search_group_id AND ug.user_pending = 0 " : '';

			if ($search_group_id)
			{
				$sql_from = ', ' . USER_GROUP_TABLE . ' ug ';
			}

			if ($ipdomain && $auth->acl_getf_global('m_info'))
			{
				if (strspn($ipdomain, 'abcdefghijklmnopqrstuvwxyz'))
				{
					$hostnames = gethostbynamel($ipdomain);

					if ($hostnames !== false)
					{
						$ips = "'" . implode('\', \'', array_map(array($db, 'sql_escape'), preg_replace('#([0-9]{1,3}\.[0-9]{1,3}[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})#', "\\1", gethostbynamel($ipdomain)))) . "'";
					}
					else
					{
						$ips = false;
					}
				}
				else
				{
					$ips = "'" . str_replace('*', '%', $db->sql_escape($ipdomain)) . "'";
				}

				if ($ips === false)
				{
					// A minor fudge but it does the job :D
					$sql_where .= " AND u.user_id = 0";
				}
				else
				{
					$ip_forums = array_keys($auth->acl_getf('m_info', true));

					$sql = 'SELECT DISTINCT poster_id
						FROM ' . POSTS_TABLE . '
						WHERE poster_ip ' . ((strpos($ips, '%') !== false) ? 'LIKE' : 'IN') . " ($ips)
							AND " . $db->sql_in_set('forum_id', $ip_forums);
					$result = $db->sql_query($sql);

					if ($row = $db->sql_fetchrow($result))
					{
						$ip_sql = array();
						do
						{
							$ip_sql[] = $row['poster_id'];
						}
						while ($row = $db->sql_fetchrow($result));

						$sql_where .= ' AND ' . $db->sql_in_set('u.user_id', $ip_sql);
					}
					else
					{
						// A minor fudge but it does the job :D
						$sql_where .= " AND u.user_id = 0";
					}
					unset($ip_forums);

					$db->sql_freeresult($result);
				}
			}
		}

		$first_char = request_var('first_char', '');

		if ($first_char == 'other')
		{
			for ($i = 97; $i < 123; $i++)
			{
				$sql_where .= ' AND u.username_clean NOT ' . $db->sql_like_expression(chr($i) . $db->any_char);
			}
		}
		else if ($first_char)
		{
			$sql_where .= ' AND u.username_clean ' . $db->sql_like_expression(substr($first_char, 0, 1) . $db->any_char);
		}

		// Are we looking at a usergroup? If so, fetch additional info
		// and further restrict the user info query
		if ($mode == 'group')
		{
			// We JOIN here to save a query for determining membership for hidden groups. ;)
			$sql = 'SELECT g.*, ug.user_id
				FROM ' . GROUPS_TABLE . ' g
				LEFT JOIN ' . USER_GROUP_TABLE . ' ug ON (ug.user_pending = 0 AND ug.user_id = ' . $user->data['user_id'] . " AND ug.group_id = $group_id)
				WHERE g.group_id = $group_id";
			$result = $db->sql_query($sql);
			$group_row = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);

			if (!$group_row)
			{
				trigger_error('NO_GROUP');
			}

			switch ($group_row['group_type'])
			{
				case GROUP_OPEN:
					$group_row['l_group_type'] = 'OPEN';
				break;

				case GROUP_CLOSED:
					$group_row['l_group_type'] = 'CLOSED';
				break;

				case GROUP_HIDDEN:
					$group_row['l_group_type'] = 'HIDDEN';

					// Check for membership or special permissions
					if (!$auth->acl_gets('a_group', 'a_groupadd', 'a_groupdel') && $group_row['user_id'] != $user->data['user_id'])
					{
						trigger_error('NO_GROUP');
					}
				break;

				case GROUP_SPECIAL:
					$group_row['l_group_type'] = 'SPECIAL';
				break;

				case GROUP_FREE:
					$group_row['l_group_type'] = 'FREE';
				break;
			}

			$avatar_img = phpbb_get_group_avatar($group_row);

			// ... same for group rank
			$rank_title = $rank_img = $rank_img_src = '';
			if ($group_row['group_rank'])
			{
				get_user_rank($group_row['group_rank'], false, $rank_title, $rank_img, $rank_img_src);

				if ($rank_img)
				{
					$rank_img .= '<br />';
				}
			}

			$template->assign_vars(array(
				'GROUP_DESC'	=> generate_text_for_display($group_row['group_desc'], $group_row['group_desc_uid'], $group_row['group_desc_bitfield'], $group_row['group_desc_options']),
				'GROUP_NAME'	=> ($group_row['group_type'] == GROUP_SPECIAL) ? $user->lang['G_' . $group_row['group_name']] : $group_row['group_name'],
				'GROUP_COLOR'	=> $group_row['group_colour'],
				'GROUP_TYPE'	=> $user->lang['GROUP_IS_' . $group_row['l_group_type']],
				'GROUP_RANK'	=> $rank_title,

				'AVATAR_IMG'	=> $avatar_img,
				'RANK_IMG'		=> $rank_img,
				'RANK_IMG_SRC'	=> $rank_img_src,

				'U_PM'			=> ($auth->acl_get('u_sendpm') && $auth->acl_get('u_masspm_group') && $group_row['group_receive_pm'] && $config['allow_privmsg'] && $config['allow_mass_pm']) ? append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=pm&amp;mode=compose&amp;g=' . $group_id) : '',)
			);

			$sql_select = ', ug.group_leader';
			$sql_from = ', ' . USER_GROUP_TABLE . ' ug ';
			$order_by = 'ug.group_leader DESC, ';

			$sql_where .= " AND ug.user_pending = 0 AND u.user_id = ug.user_id AND ug.group_id = $group_id";
			$sql_where_data = " AND u.user_id = ug.user_id AND ug.group_id = $group_id";
		}

		// Sorting and order
		if (!isset($sort_key_sql[$sort_key]))
		{
			$sort_key = $default_key;
		}

		$order_by .= $sort_key_sql[$sort_key] . ' ' . (($sort_dir == 'a') ? 'ASC' : 'DESC');

		// Unfortunately we must do this here for sorting by rank, else the sort order is applied wrongly
		if ($sort_key == 'm')
		{
			$order_by .= ', u.user_posts DESC';
		}

		// Count the users ...
		if ($sql_where)
		{
			$sql = 'SELECT COUNT(u.user_id) AS total_users
				FROM ' . USERS_TABLE . " u$sql_from
				WHERE u.user_type IN (" . USER_NORMAL . ', ' . USER_FOUNDER . ")
				$sql_where";
			$result = $db->sql_query($sql);
			$total_users = (int) $db->sql_fetchfield('total_users');
			$db->sql_freeresult($result);
		}
		else
		{
			$total_users = $config['num_users'];
		}

		// Build a relevant pagination_url
		$params = $sort_params = array();

		// We do not use request_var() here directly to save some calls (not all variables are set)
		$check_params = array(
			'g'				=> array('g', 0),
			'sk'			=> array('sk', $default_key),
			'sd'			=> array('sd', 'a'),
			'form'			=> array('form', ''),
			'field'			=> array('field', ''),
			'select_single'	=> array('select_single', $select_single),
			'username'		=> array('username', '', true),
			'email'			=> array('email', ''),
			'jabber'		=> array('jabber', ''),
			'search_group_id'	=> array('search_group_id', 0),
			'joined_select'	=> array('joined_select', 'lt'),
			'active_select'	=> array('active_select', 'lt'),
			'count_select'	=> array('count_select', 'eq'),
			'joined'		=> array('joined', ''),
			'active'		=> array('active', ''),
			'count'			=> (request_var('count', '') !== '') ? array('count', 0) : array('count', ''),
			'ip'			=> array('ip', ''),
			'first_char'	=> array('first_char', ''),
		);

		$u_first_char_params = array();
		foreach ($check_params as $key => $call)
		{
			if (!isset($_REQUEST[$key]))
			{
				continue;
			}

			$param = call_user_func_array('request_var', $call);
			$param = urlencode($key) . '=' . ((is_string($param)) ? urlencode($param) : $param);
			$params[] = $param;

			if ($key != 'first_char')
			{
				$u_first_char_params[] = $param;
			}
			if ($key != 'sk' && $key != 'sd')
			{
				$sort_params[] = $param;
			}
		}

		$u_hide_find_member = append_sid("{$phpbb_root_path}memberlist.$phpEx", "start=$start" . (!empty($params) ? '&amp;' . implode('&amp;', $params) : ''));

		if ($mode)
		{
			$params[] = "mode=$mode";
			$u_first_char_params[] = "mode=$mode";
		}
		$sort_params[] = "mode=$mode";

		$pagination_url = append_sid("{$phpbb_root_path}memberlist.$phpEx", implode('&amp;', $params));
		$sort_url = append_sid("{$phpbb_root_path}memberlist.$phpEx", implode('&amp;', $sort_params));

		unset($search_params, $sort_params);

		$u_first_char_params = implode('&amp;', $u_first_char_params);
		$u_first_char_params .= ($u_first_char_params) ? '&amp;' : '';

		$first_characters = array();
		$first_characters[''] = $user->lang['ALL'];
		for ($i = 97; $i < 123; $i++)
		{
			$first_characters[chr($i)] = chr($i - 32);
		}
		$first_characters['other'] = $user->lang['OTHER'];

		foreach ($first_characters as $char => $desc)
		{
			$template->assign_block_vars('first_char', array(
				'DESC'			=> $desc,
				'VALUE'			=> $char,
				'S_SELECTED'	=> ($first_char == $char) ? true : false,
				'U_SORT'		=> append_sid("{$phpbb_root_path}memberlist.$phpEx", $u_first_char_params . 'first_char=' . $char) . '#memberlist',
			));
		}

		// Some search user specific data
		if (($mode == '' || $mode == 'searchuser') && ($config['load_search'] || $auth->acl_get('a_')))
		{
			$group_selected = request_var('search_group_id', 0);
			$s_group_select = '<option value="0"' . ((!$group_selected) ? ' selected="selected"' : '') . '>&nbsp;</option>';
			$group_ids = array();

			/**
			* @todo add this to a separate function (function is responsible for returning the groups the user is able to see based on the users group membership)
			*/

			if ($auth->acl_gets('a_group', 'a_groupadd', 'a_groupdel'))
			{
				$sql = 'SELECT group_id, group_name, group_type
					FROM ' . GROUPS_TABLE;

				if (!$config['coppa_enable'])
				{
					$sql .= " WHERE group_name <> 'REGISTERED_COPPA'";
				}

				$sql .= ' ORDER BY group_name ASC';
			}
			else
			{
				$sql = 'SELECT g.group_id, g.group_name, g.group_type
					FROM ' . GROUPS_TABLE . ' g
					LEFT JOIN ' . USER_GROUP_TABLE . ' ug
						ON (
							g.group_id = ug.group_id
							AND ug.user_id = ' . $user->data['user_id'] . '
							AND ug.user_pending = 0
						)
					WHERE (g.group_type <> ' . GROUP_HIDDEN . ' OR ug.user_id = ' . $user->data['user_id'] . ')';

				if (!$config['coppa_enable'])
				{
					$sql .= " AND g.group_name <> 'REGISTERED_COPPA'";
				}

				$sql .= ' ORDER BY g.group_name ASC';
			}
			$result = $db->sql_query($sql);

			while ($row = $db->sql_fetchrow($result))
			{
				$group_ids[] = $row['group_id'];
				$s_group_select .= '<option value="' . $row['group_id'] . '"' . (($group_selected == $row['group_id']) ? ' selected="selected"' : '') . '>' . (($row['group_type'] == GROUP_SPECIAL) ? $user->lang['G_' . $row['group_name']] : $row['group_name']) . '</option>';
			}
			$db->sql_freeresult($result);

			if ($group_selected !== 0 && !in_array($group_selected, $group_ids))
			{
				trigger_error('NO_GROUP');
			}

			$template->assign_vars(array(
				'USERNAME'	=> $username,
				'EMAIL'		=> $email,
				'JABBER'	=> $jabber,
				'JOINED'	=> implode('-', $joined),
				'ACTIVE'	=> implode('-', $active),
				'COUNT'		=> $count,
				'IP'		=> $ipdomain,

				'S_IP_SEARCH_ALLOWED'	=> ($auth->acl_getf_global('m_info')) ? true : false,
				'S_EMAIL_SEARCH_ALLOWED'=> ($auth->acl_get('a_user')) ? true : false,
				'S_IN_SEARCH_POPUP'		=> ($form && $field) ? true : false,
				'S_SEARCH_USER'			=> ($mode == 'searchuser' || ($mode == '' && $submit)),
				'S_FORM_NAME'			=> $form,
				'S_FIELD_NAME'			=> $field,
				'S_SELECT_SINGLE'		=> $select_single,
				'S_COUNT_OPTIONS'		=> $s_find_count,
				'S_SORT_OPTIONS'		=> $s_sort_key,
				'S_JOINED_TIME_OPTIONS'	=> $s_find_join_time,
				'S_ACTIVE_TIME_OPTIONS'	=> $s_find_active_time,
				'S_GROUP_SELECT'		=> $s_group_select,
				'S_USER_SEARCH_ACTION'	=> append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=searchuser&amp;form=$form&amp;field=$field"))
			);
		}

		$start = $pagination->validate_start($start, $config['topics_per_page'], $config['num_users']);

		// Get us some users :D
		$sql = "SELECT u.user_id
			FROM " . USERS_TABLE . " u
				$sql_from
			WHERE u.user_type IN (" . USER_NORMAL . ', ' . USER_FOUNDER . ")
				$sql_where
			ORDER BY $order_by";
		$result = $db->sql_query_limit($sql, $config['topics_per_page'], $start);

		$user_list = array();
		while ($row = $db->sql_fetchrow($result))
		{
			$user_list[] = (int) $row['user_id'];
		}
		$db->sql_freeresult($result);

		// Load custom profile fields
		if ($config['load_cpf_memberlist'])
		{
			$cp = $phpbb_container->get('profilefields.manager');

			$cp_row = $cp->generate_profile_fields_template_headlines('field_show_on_ml');
			foreach ($cp_row as $profile_field)
			{
				$template->assign_block_vars('custom_fields', $profile_field);
			}
		}

		$leaders_set = false;
		// So, did we get any users?
		if (sizeof($user_list))
		{
			// Session time?! Session time...
			$sql = 'SELECT session_user_id, MAX(session_time) AS session_time
				FROM ' . SESSIONS_TABLE . '
				WHERE session_time >= ' . (time() - $config['session_length']) . '
					AND ' . $db->sql_in_set('session_user_id', $user_list) . '
				GROUP BY session_user_id';
			$result = $db->sql_query($sql);

			$session_times = array();
			while ($row = $db->sql_fetchrow($result))
			{
				$session_times[$row['session_user_id']] = $row['session_time'];
			}
			$db->sql_freeresult($result);

			// Do the SQL thang
			if ($mode == 'group')
			{
				$sql = "SELECT u.*
						$sql_select
					FROM " . USERS_TABLE . " u
						$sql_from
					WHERE " . $db->sql_in_set('u.user_id', $user_list) . "
						$sql_where_data";
			}
			else
			{
				$sql = 'SELECT *
					FROM ' . USERS_TABLE . '
					WHERE ' . $db->sql_in_set('user_id', $user_list);
			}
			$result = $db->sql_query($sql);

			$id_cache = array();
			while ($row = $db->sql_fetchrow($result))
			{
				$row['session_time'] = (!empty($session_times[$row['user_id']])) ? $session_times[$row['user_id']] : 0;
				$row['last_visit'] = (!empty($row['session_time'])) ? $row['session_time'] : $row['user_lastvisit'];

				$id_cache[$row['user_id']] = $row;
			}
			$db->sql_freeresult($result);

			// Load custom profile fields
			if ($config['load_cpf_memberlist'])
			{
				// Grab all profile fields from users in id cache for later use - similar to the poster cache
				$profile_fields_cache = $cp->grab_profile_fields_data($user_list);

				// Filter the fields we don't want to show
				foreach ($profile_fields_cache as $user_id => $user_profile_fields)
				{
					foreach ($user_profile_fields as $field_ident => $profile_field)
					{
						if (!$profile_field['data']['field_show_on_ml'])
						{
							unset($profile_fields_cache[$user_id][$field_ident]);
						}
					}
				}
			}

			// If we sort by last active date we need to adjust the id cache due to user_lastvisit not being the last active date...
			if ($sort_key == 'l')
			{
//				uasort($id_cache, create_function('$first, $second', "return (\$first['last_visit'] == \$second['last_visit']) ? 0 : ((\$first['last_visit'] < \$second['last_visit']) ? $lesser_than : ($lesser_than * -1));"));
				usort($user_list,  '_sort_last_active');
			}

			for ($i = 0, $end = sizeof($user_list); $i < $end; ++$i)
			{
				$user_id = $user_list[$i];
				$row = $id_cache[$user_id];
				$is_leader = (isset($row['group_leader']) && $row['group_leader']) ? true : false;
				$leaders_set = ($leaders_set || $is_leader);

				$cp_row = array();
				if ($config['load_cpf_memberlist'])
				{
					$cp_row = (isset($profile_fields_cache[$user_id])) ? $cp->generate_profile_fields_template_data($profile_fields_cache[$user_id], false) : array();
				}

				$memberrow = array_merge(show_profile($row), array(
					'ROW_NUMBER'		=> $i + ($start + 1),

					'S_CUSTOM_PROFILE'	=> (isset($cp_row['row']) && sizeof($cp_row['row'])) ? true : false,
					'S_GROUP_LEADER'	=> $is_leader,

					'U_VIEW_PROFILE'	=> get_username_string('profile', $user_id, $row['username']),
				));

				if (isset($cp_row['row']) && sizeof($cp_row['row']))
				{
					$memberrow = array_merge($memberrow, $cp_row['row']);
				}

				$template->assign_block_vars('memberrow', $memberrow);

				if (isset($cp_row['blockrow']) && sizeof($cp_row['blockrow']))
				{
					foreach ($cp_row['blockrow'] as $field_data)
					{
						$template->assign_block_vars('memberrow.custom_fields', $field_data);
					}
				}

				unset($id_cache[$user_id]);
			}
		}

		$pagination->generate_template_pagination($pagination_url, 'pagination', 'start', $total_users, $config['topics_per_page'], $start);

		// Generate page
		$template->assign_vars(array(
			'TOTAL_USERS'	=> $user->lang('LIST_USERS', (int) $total_users),

			'PROFILE_IMG'	=> $user->img('icon_user_profile', $user->lang['PROFILE']),
			'PM_IMG'		=> $user->img('icon_contact_pm', $user->lang['SEND_PRIVATE_MESSAGE']),
			'EMAIL_IMG'		=> $user->img('icon_contact_email', $user->lang['EMAIL']),
			'JABBER_IMG'	=> $user->img('icon_contact_jabber', $user->lang['JABBER']),
			'SEARCH_IMG'	=> $user->img('icon_user_search', $user->lang['SEARCH']),

			'U_FIND_MEMBER'			=> ($config['load_search'] || $auth->acl_get('a_')) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=searchuser' . (($start) ? "&amp;start=$start" : '') . (!empty($params) ? '&amp;' . implode('&amp;', $params) : '')) : '',
			'U_HIDE_FIND_MEMBER'	=> ($mode == 'searchuser' || ($mode == '' && $submit)) ? $u_hide_find_member : '',
			'U_LIVE_SEARCH'			=> ($config['allow_live_searches']) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=livesearch') : false,
			'U_SORT_USERNAME'		=> $sort_url . '&amp;sk=a&amp;sd=' . (($sort_key == 'a' && $sort_dir == 'a') ? 'd' : 'a'),
			'U_SORT_JOINED'			=> $sort_url . '&amp;sk=c&amp;sd=' . (($sort_key == 'c' && $sort_dir == 'a') ? 'd' : 'a'),
			'U_SORT_POSTS'			=> $sort_url . '&amp;sk=d&amp;sd=' . (($sort_key == 'd' && $sort_dir == 'a') ? 'd' : 'a'),
			'U_SORT_EMAIL'			=> $sort_url . '&amp;sk=e&amp;sd=' . (($sort_key == 'e' && $sort_dir == 'a') ? 'd' : 'a'),
			'U_SORT_ACTIVE'			=> ($auth->acl_get('u_viewonline')) ? $sort_url . '&amp;sk=l&amp;sd=' . (($sort_key == 'l' && $sort_dir == 'a') ? 'd' : 'a') : '',
			'U_SORT_RANK'			=> $sort_url . '&amp;sk=m&amp;sd=' . (($sort_key == 'm' && $sort_dir == 'a') ? 'd' : 'a'),
			'U_LIST_CHAR'			=> $sort_url . '&amp;sk=a&amp;sd=' . (($sort_key == 'l' && $sort_dir == 'a') ? 'd' : 'a'),

			'S_SHOW_GROUP'		=> ($mode == 'group') ? true : false,
			'S_VIEWONLINE'		=> $auth->acl_get('u_viewonline'),
			'S_LEADERS_SET'		=> $leaders_set,
			'S_MODE_SELECT'		=> $s_sort_key,
			'S_ORDER_SELECT'	=> $s_sort_dir,
			'S_MODE_ACTION'		=> $pagination_url)
		);
}

// Output the page
page_header($page_title);

$template->set_filenames(array(
	'body' => $template_html)
);
make_jumpbox(append_sid("{$phpbb_root_path}viewforum.$phpEx"));

page_footer();

/**
* Prepare profile data
*/
function show_profile($data, $user_notes_enabled = false, $warn_user_enabled = false)
{
	global $config, $auth, $template, $user, $phpEx, $phpbb_root_path, $phpbb_dispatcher;

	$username = $data['username'];
	$user_id = $data['user_id'];

	$rank_title = $rank_img = $rank_img_src = '';
	get_user_rank($data['user_rank'], (($user_id == ANONYMOUS) ? false : $data['user_posts']), $rank_title, $rank_img, $rank_img_src);

	if ((!empty($data['user_allow_viewemail']) && $auth->acl_get('u_sendemail')) || $auth->acl_get('a_user'))
	{
		$email = ($config['board_email_form'] && $config['email_enable']) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=email&amp;u=' . $user_id) : (($config['board_hide_emails'] && !$auth->acl_get('a_user')) ? '' : 'mailto:' . $data['user_email']);
	}
	else
	{
		$email = '';
	}

	if ($config['load_onlinetrack'])
	{
		$update_time = $config['load_online_time'] * 60;
		$online = (time() - $update_time < $data['session_time'] && ((isset($data['session_viewonline']) && $data['session_viewonline']) || $auth->acl_get('u_viewonline'))) ? true : false;
	}
	else
	{
		$online = false;
	}

	if ($data['user_allow_viewonline'] || $auth->acl_get('u_viewonline'))
	{
		$last_active = (!empty($data['session_time'])) ? $data['session_time'] : $data['user_lastvisit'];
	}
	else
	{
		$last_active = '';
	}

	$age = '';

	if ($config['allow_birthdays'] && $data['user_birthday'])
	{
		list($bday_day, $bday_month, $bday_year) = array_map('intval', explode('-', $data['user_birthday']));

		if ($bday_year)
		{
			$now = $user->create_datetime();
			$now = phpbb_gmgetdate($now->getTimestamp() + $now->getOffset());

			$diff = $now['mon'] - $bday_month;
			if ($diff == 0)
			{
				$diff = ($now['mday'] - $bday_day < 0) ? 1 : 0;
			}
			else
			{
				$diff = ($diff < 0) ? 1 : 0;
			}

			$age = max(0, (int) ($now['year'] - $bday_year - $diff));
		}
	}

	if (!function_exists('phpbb_get_banned_user_ids'))
	{
		include($phpbb_root_path . 'includes/functions_user.' . $phpEx);
	}

	// Can this user receive a Private Message?
	$can_receive_pm = (
		// They must be a "normal" user
		$data['user_type'] != USER_IGNORE &&

		// They must not be deactivated by the administrator
		($data['user_type'] != USER_INACTIVE || $data['user_inactive_reason'] != INACTIVE_MANUAL) &&

		// They must be able to read PMs
		sizeof($auth->acl_get_list($user_id, 'u_readpm')) &&

		// They must not be permanently banned
		!sizeof(phpbb_get_banned_user_ids($user_id, false)) &&

		// They must allow users to contact via PM
		(($auth->acl_gets('a_', 'm_') || $auth->acl_getf_global('m_')) || $data['user_allow_pm'])
	);

	// Dump it out to the template
	$template_data = array(
		'AGE'			=> $age,
		'RANK_TITLE'	=> $rank_title,
		'JOINED'		=> $user->format_date($data['user_regdate']),
		'LAST_ACTIVE'	=> (empty($last_active)) ? ' - ' : $user->format_date($last_active),
		'POSTS'			=> ($data['user_posts']) ? $data['user_posts'] : 0,
		'WARNINGS'		=> isset($data['user_warnings']) ? $data['user_warnings'] : 0,

		'USERNAME_FULL'		=> get_username_string('full', $user_id, $username, $data['user_colour']),
		'USERNAME'			=> get_username_string('username', $user_id, $username, $data['user_colour']),
		'USER_COLOR'		=> get_username_string('colour', $user_id, $username, $data['user_colour']),
		'U_VIEW_PROFILE'	=> get_username_string('profile', $user_id, $username, $data['user_colour']),

		'A_USERNAME'		=> addslashes(get_username_string('username', $user_id, $username, $data['user_colour'])),

		'AVATAR_IMG'		=> phpbb_get_user_avatar($data),
		'ONLINE_IMG'		=> (!$config['load_onlinetrack']) ? '' : (($online) ? $user->img('icon_user_online', 'ONLINE') : $user->img('icon_user_offline', 'OFFLINE')),
		'S_ONLINE'			=> ($config['load_onlinetrack'] && $online) ? true : false,
		'RANK_IMG'			=> $rank_img,
		'RANK_IMG_SRC'		=> $rank_img_src,
		'S_JABBER_ENABLED'	=> ($config['jab_enable']) ? true : false,

		'S_WARNINGS'	=> ($auth->acl_getf_global('m_') || $auth->acl_get('m_warn')) ? true : false,

		'U_SEARCH_USER' => ($auth->acl_get('u_search')) ? append_sid("{$phpbb_root_path}search.$phpEx", "author_id=$user_id&amp;sr=posts") : '',
		'U_NOTES'		=> ($user_notes_enabled && $auth->acl_getf_global('m_')) ? append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=notes&amp;mode=user_notes&amp;u=' . $user_id, true, $user->session_id) : '',
		'U_WARN'		=> ($warn_user_enabled && $auth->acl_get('m_warn')) ? append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=warn&amp;mode=warn_user&amp;u=' . $user_id, true, $user->session_id) : '',
		'U_PM'			=> ($config['allow_privmsg'] && $auth->acl_get('u_sendpm') && $can_receive_pm) ? append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=pm&amp;mode=compose&amp;u=' . $user_id) : '',
		'U_EMAIL'		=> $email,
		'U_JABBER'		=> ($data['user_jabber'] && $auth->acl_get('u_sendim')) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=contact&amp;action=jabber&amp;u=' . $user_id) : '',

		'USER_JABBER'		=> $data['user_jabber'],
		'USER_JABBER_IMG'	=> ($data['user_jabber']) ? $user->img('icon_contact_jabber', $data['user_jabber']) : '',

		'L_SEND_EMAIL_USER' => $user->lang('SEND_EMAIL_USER', $username),
		'L_CONTACT_USER'	=> $user->lang('CONTACT_USER', $username),
		'L_VIEWING_PROFILE' => $user->lang('VIEWING_PROFILE', $username),
	);

	/**
	* Preparing a user's data before displaying it in profile and memberlist
	*
	* @event core.memberlist_prepare_profile_data
	* @var	array	data				Array with user's data
	* @var	array	template_data		Template array with user's data
	* @since 3.1.0-a1
	*/
	$vars = array('data', 'template_data');
	extract($phpbb_dispatcher->trigger_event('core.memberlist_prepare_profile_data', compact($vars)));

	return $template_data;
}

function _sort_last_active($first, $second)
{
	global $id_cache, $sort_dir;

	$lesser_than = ($sort_dir === 'd') ? -1 : 1;

	if (isset($id_cache[$first]['group_leader']) && $id_cache[$first]['group_leader'] && (!isset($id_cache[$second]['group_leader']) || !$id_cache[$second]['group_leader']))
	{
		return -1;
	}
	else if (isset($id_cache[$second]['group_leader']) && (!isset($id_cache[$first]['group_leader']) || !$id_cache[$first]['group_leader']) && $id_cache[$second]['group_leader'])
	{
		return 1;
	}
	else
	{
		return $lesser_than * (int) ($id_cache[$first]['last_visit'] - $id_cache[$second]['last_visit']);
	}
}
