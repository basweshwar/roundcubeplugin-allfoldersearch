<?php

/**
 * All Folder Search
 *
 * Add the option to search all imap folders
 *
 * Known limitations:
 *   - no message dragging or deleting allowed while in an all folder search
 *   - viewing a message then going back to the message list
 *       x message list doesnt exist at this point so its not as easy as calling the plugin's list function
 *
 * @version 0.6
 * @author Ryan Ostrander
 */

class all_folder_search extends rcube_plugin
{
    public $task = 'mail';

    function init()
    {
        $this->add_hook('search_override', array($this, 'search_override'));
        $this->add_hook('list_override', array($this, 'list_override'));
        $this->add_hook('mark_override', array($this, 'mark_override'));
        $this->add_hook('mov_del_override', array($this, 'mov_del_override'));
        $this->add_hook('startup', array($this, 'startup'));

        $this->include_script('client.js');

        // let php know when we are no longer in an all folder search so it can knows when not to override
        $this->register_action('plugin.not_all_folder_search', array($this, 'not_all_folder_search'));
    }

    /**
     * Called when the application is initialized
     *
     * @access  public
     */
    function startup($args)
    {
        if($args['get']['_search'] == 'ALLFOLDERSSEARCHREQ')
        {
            if($args['action'] == 'show' || $args['action'] == 'preview')
                $args['get']['_uid'] = $_SESSION['all_folder_search']['uid_mboxes'][$args['get']['_uid']]['uid'];
        }
        else if($_SESSION['all_folder_search']['uid_mboxes'])
        {
            // if action is empty then the page has been refreshed
            if(!$args['action'])
                $_SESSION['all_folder_search']['uid_mboxes'] = 0;
        }

        return $args;
    }

    /**
     * Called when a search is initiated
     *
     * @access  public
     */
    function search_override($args)
    {
	global $RCMAIL;
        // return if not an all folder search
	//BUG
        if(strpos(get_input_value('_headers', RCUBE_INPUT_GET), 'all_folder_search') === FALSE)
        {
            // use roundcube's search.inc
            $args['abort'] = false;
        }
        else
        {
            // reset list_page and old search results
		
	     //BUG Original
            $RCMAIL->imap->set_page(1);
            $RCMAIL->imap->set_search_set(NULL);
            $_SESSION['page'] = 1;
            $page = get_input_value('_page', RCUBE_INPUT_GET);
            $page = $page ? $page : 1;
	    

            // get search string
            $str = get_input_value('_q', RCUBE_INPUT_GET);
            $filter = get_input_value('_filter', RCUBE_INPUT_GET);
            $headers = get_input_value('_headers', RCUBE_INPUT_GET);

            // add list filter string
            $search_str = $filter && $filter != 'ALL' ? $filter : '';

            $_SESSION['search_filter'] = $filter;

            // Check the search string for type of search
            if (preg_match("/^from:.*/i", $str))
            {
              list(,$srch) = explode(":", $str);
              $subject['from'] = "HEADER FROM";
            }
            else if (preg_match("/^to:.*/i", $str))
            {
              list(,$srch) = explode(":", $str);
              $subject['to'] = "HEADER TO";
            }
            else if (preg_match("/^cc:.*/i", $str))
            {
              list(,$srch) = explode(":", $str);
              $subject['cc'] = "HEADER CC";
            }
            else if (preg_match("/^bcc:.*/i", $str))
            {
              list(,$srch) = explode(":", $str);
              $subject['bcc'] = "HEADER BCC";
            }
            else if (preg_match("/^subject:.*/i", $str))
            {
              list(,$srch) = explode(":", $str);
              $subject['subject'] = "HEADER SUBJECT";
            }
            else if (preg_match("/^body:.*/i", $str))
            {
              list(,$srch) = explode(":", $str);
              $subject['text'] = "TEXT";
            }
            // search in subject and sender by default
            else if(trim($str))
            {
              if ($headers) {
                $headers = explode(',', $headers);
                foreach($headers as $header)
                  switch ($header) {
                    case 'text': $subject['text'] = 'TEXT'; break;
                    case 'all_folder_search': break;
                    default: $subject[$header] = 'HEADER '.$header;
                  }
              } else {
                $subject['subject'] = 'HEADER SUBJECT';
              }
            }

            $search = $srch ? trim($srch) : trim($str);

            if ($subject) {
              $search_str .= str_repeat(' OR', count($subject)-1);
              foreach ($subject as $sub)
                $search_str .= sprintf(" %s {%d}\r\n%s", $sub, strlen($search), $search);
              $_SESSION['search_mods'] = $subject;
            }

            $search_str = trim($search_str);
            $count = 0;
            $result_h = Array();
            $tmp_page_size = $RCMAIL->imap->page_size;
            $RCMAIL->imap->set_pagesize(500);

            if($use_saved_list && $_SESSION['all_folder_search']['uid_mboxes'])
                $result_h = $this->get_search_result();
            else{
                $result_h = $this->perform_search($search_str);
	    }

            $RCMAIL->output->set_env('all_folder_search_active', 1);

            $RCMAIL->imap->set_pagesize($tmp_page_size);
            $count = count($result_h);

            $this->sort_search_result($result_h);

            $result_h = $this->get_paged_result($result_h, $page);

            // Make sure we got the headers
            if (!empty($result_h))
            {
              rcmail_js_message_list($result_h, false , false);

              if ($search_str)
                $RCMAIL->output->show_message('searchsuccessful', 'confirmation', array('nr' => $count));

	      $RCMAIL->output->command('list_mailbox');
            }
            else
            {
              $RCMAIL->output->show_message('searchnomatch', 'notice');
            }

            // update message count display
            $RCMAIL->output->set_env('search_request', "ALLFOLDERSSEARCHREQ");
            $RCMAIL->output->set_env('messagecount', $count);
            $RCMAIL->output->set_env('pagecount', ceil($count/$RCMAIL->imap->page_size));
            $RCMAIL->output->command('set_rowcount', rcmail_get_messagecount_text($count, $page));

            // dont execute roundcube's search.inc
            $args['abort'] = true;
            $RCMAIL->output->send();
        }

        return $args;
    }

    /**
     * Called whenever the messagelist is refreshed, sorted, page jumped, etc.
     *
     * @access  public
     */
    function list_override($args)
    {
        global $RCMAIL;

        // return if not an all folder search
        //   cant check using session here because not_all_folder_search
        //   is called at the same time
        if(get_input_value('_search', RCUBE_INPUT_GET) != 'ALLFOLDERSSEARCHREQ')
        {
            // use roundcube's list.inc
            $args['abort'] = false;
        }
        else
        {
            // is there a sort type for this request?
            if ($sort = get_input_value('_sort', RCUBE_INPUT_GET))
            {
                // yes, so set the sort vars
                list($sort_col, $sort_order) = explode('_', $sort);

                // set session vars for sort (so next page and task switch know how to sort)
                $save_arr = array();
                $_SESSION['sort_col'] = $save_arr['message_sort_col'] = $sort_col;
                $_SESSION['sort_order'] = $save_arr['message_sort_order'] = $sort_order;
            }
            else
            {
                // use session settings if set, defaults if not
                $sort_col   = isset($_SESSION['sort_col'])   ? $_SESSION['sort_col']   : $CONFIG['message_sort_col'];
                $sort_order = isset($_SESSION['sort_order']) ? $_SESSION['sort_order'] : $CONFIG['message_sort_order'];
            }

            $page = get_input_value('_page', RCUBE_INPUT_GET);
            $page = $page ? $page : $_SESSION['page'];
            $_SESSION['page'] = $page;

            $result_h = $this->get_search_result();
            $count = count($result_h);

            $this->sort_search_result($result_h);
            $result_h = $this->get_paged_result($result_h, $page);
            rcmail_js_message_list($result_h);

            $RCMAIL->output->command('set_rowcount', rcmail_get_messagecount_text($count, $page));
            $RCMAIL->output->send();

            // dont execute roundcube's list.inc
            $args['abort'] = true;
        }

        return $args;
    }

    /**
     * Called when message statuses are modified or they have been flagged
     *
     * @access  public
     */
    function mark_override($args)
    {
        global $RCMAIL;

        // return if not an all folder search
        if(!$_SESSION['all_folder_search']['uid_mboxes'])
        {
            // use roundcube's mark.inc
            $args['abort'] = false;
        }
        else
        {
            $uids = $args['uids'];
            $flag = $args['flag'];

            if ($flag == 'DELETED' && $CONFIG['skip_deleted'] && $_POST['_from'] != 'show')
            {
                // count messages before changing anything
                $old_count = count($_SESSION['all_folder_search']['uid_mboxes']);
                $old_pages = ceil($old_count / $RCMAIL->imap->page_size);
                $count = sizeof(explode(',', $uids));
            }

            $uids = explode(',', $uids);

            // mark each uid individually because the mailboxes may differ
            foreach($uids as $uid)
            {
                $mbox = $_SESSION['all_folder_search']['uid_mboxes'][$uid]['mbox'];
                $RCMAIL->imap->set_mailbox($mbox);
                $marked = $RCMAIL->imap->set_flag($_SESSION['all_folder_search']['uid_mboxes'][$uid]['uid'], $flag);

                if ($marked == -1)
                {
                    // send error message
                    if ($_POST['_from'] != 'show')
                        $RCMAIL->output->command('list_mailbox');

                    $RCMAIL->output->show_message('errormarking', 'error');
                    $RCMAIL->output->send();
                    return $args;
                }
            }

            if($flag == 'DELETED' && $CONFIG['read_when_deleted'] && !empty($_POST['_ruid']))
            {
                $uids = get_input_value('_ruid', RCUBE_INPUT_POST);
                $uids = explode(',', $uids);

                foreach($uids as $uid)
                {
                    $mbox = $_SESSION['all_folder_search']['uid_mboxes'][$uid]['mbox'];
                    $RCMAIL->imap->set_mailbox($mbox);
                    $read = $RCMAIL->imap->set_flag($_SESSION['all_folder_search']['uid_mboxes'][$uid]['uid'], 'SEEN');

                    if ($read != -1 && !$CONFIG['skip_deleted'])
                        $RCMAIL->output->command('flag_deleted_as_read', $uid);
                }
            }

            if ($flag == 'SEEN' || $flag == 'UNSEEN' || ($flag == 'DELETED' && !$CONFIG['skip_deleted']))
            {
                // just update unread count for all mailboxes, easier than figuring out which were changed
                $mbox_names = $RCMAIL->imap->list_mailboxes();

                foreach($mbox_names as $mbox)
                    $RCMAIL->output->command('set_unread_count', $mbox, $RCMAIL->imap->messagecount($mbox, 'UNSEEN'), ($mbox == 'INBOX'));
            }
            else if ($flag == 'DELETED' && $CONFIG['skip_deleted'])
            {
                if ($_POST['_from'] == 'show')
                {
                    if ($next = get_input_value('_next_uid', RCUBE_INPUT_GPC))
                        $RCMAIL->output->command('show_message', $next);
                    else
                        $RCMAIL->output->command('command', 'list');
                }
                else
                {

                    // refresh saved search set after moving some messages
                    if (($search_request = get_input_value('_search', RCUBE_INPUT_GPC)) && $_SESSION['all_folder_search']['uid_mboxes'])
                    {
                        $_SESSION['search'][$search_request] = $this->perform_search($RCMAIL->imap->search_string);
                    }

                    $msg_count      = count($_SESSION['all_folder_search']['uid_mboxes']);
                    $pages          = ceil($msg_count / $RCMAIL->imap->page_size);
                    $nextpage_count = $old_count - $RCMAIL->imap->page_size * $_SESSION['page'];
                    $remaining      = $msg_count - $RCMAIL->imap->page_size * ($_SESSION['page'] - 1);

                    // jump back one page (user removed the whole last page)
                    if ($_SESSION['page'] > 1 && $nextpage_count <= 0 && $remaining == 0)
                    {
                        $RCMAIL->imap->set_page($_SESSION['page']-1);
                        $_SESSION['page'] = $RCMAIL->imap->list_page;
                        $jump_back = true;
                    }

                    // update message count display
                    $RCMAIL->output->set_env('messagecount', $msg_count);
                    $RCMAIL->output->set_env('current_page', $RCMAIL->imap->list_page);
                    $RCMAIL->output->set_env('pagecount', $pages);

                    // update mailboxlist
                    foreach($RCMAIL->imap->list_mailboxes() as $mbox)
                    {
                        $unseen_count = $msg_count ? $RCMAIL->imap->messagecount($mbox, 'UNSEEN') : 0;
                        $RCMAIL->output->command('set_unread_count', $mbox, $unseen_count, ($mbox == 'INBOX'));
                    }
                    $RCMAIL->output->command('set_rowcount', rcmail_get_messagecount_text($msg_count));

                    // add new rows from next page (if any)
                    if (($jump_back || $nextpage_count > 0))
                    {
                        $sort_col   = isset($_SESSION['sort_col'])   ? $_SESSION['sort_col']   : $CONFIG['message_sort_col'];
                        $sort_order = isset($_SESSION['sort_order']) ? $_SESSION['sort_order'] : $CONFIG['message_sort_order'];

                        //$a_headers = $IMAP->list_headers($mbox, NULL, $sort_col, $sort_order, $count);
                        //$this->sort_order == 'DESC' ? 0 : -$slice
                        $a_headers = $this->get_search_result();
                        $this->sort_search_result($a_headers);
                        $a_headers = array_slice($a_headers, $sort_order == 'DESC' ? 0 : -$count, $count);
                        rcmail_js_message_list($a_headers, false, false);
                    }
                }
            }

            $RCMAIL->output->send();

            // dont execute roundcube's mark.inc
            $args['abort'] = true;
        }

        return $args;
    }

    /**
     * Called when messages are dragged
     *
     * @access  public
     */
    function mov_del_override($args)
    {
        global $RCMAIL;

        // return if not an all folder search
        if(!$_SESSION['all_folder_search']['uid_mboxes'])
        {
            // use roundcube's mov_del.inc
            $args['abort'] = false;
        }
        else
        {
            // count messages before changing anything
            //$old_count = $IMAP->messagecount();
            //$old_pages = ceil($old_count / $IMAP->page_size);
            // move messages
            if (!empty($_POST['_uid']) && !empty($_POST['_target_mbox']))
            {
                $count = sizeof(explode(',', ($uids = get_input_value('_uid', RCUBE_INPUT_POST))));
                $target = get_input_value('_target_mbox', RCUBE_INPUT_POST);
                $mbox = $_SESSION['all_folder_search']['uid_mboxes'][$uids[0]];
                $RCMAIL->imap->set_mailbox($mbox);

                // flag messages as read before moving them
                if ($CONFIG['read_when_deleted'] && $target == $CONFIG['trash_mbox'])
                    $RCMAIL->imap->set_flag($uids, 'SEEN');

console("moving $uids from $mbox to $target");
                $moved = $RCMAIL->imap->move_message($uids, $target, $mbox);

                if (!$moved) {
                    // send error message
                if ($_POST['_from'] != 'show')
                      $RCMAIL->output->command('list_mailbox');
                    $RCMAIL->output->show_message('errormoving', 'error');
                    $RCMAIL->output->send();
                    exit;
                }
                else
                    $RCMAIL->output->show_message("Successfully moved message from $mbox to $target");

                $addrows = true;
            }

            // send response
            $RCMAIL->output->send();

            // dont execute roundcube's mov_del.inc
            $args['abort'] = true;
        }

        return $args;
    }

    /**
     * Nullify all folder search results
     *
     * By nullifying we are letting the plugin know we are no longer in an all folder search
     *
     * @access  public
     */
    function not_all_folder_search()
    {
        $_SESSION['all_folder_search']['uid_mboxes'] = 0;
    }

    /**
     * Perform the all folder search
     *
     * @param   string   Search string
     * @return  array    Indexed array with message header objects
     * @access  public
     */
    function perform_search($search_string)
    {
        global $RCMAIL;
        $result_h = Array();
        $uid_mboxes = Array();
        $id = 1;

        // Search all folders and build a final set
        foreach($RCMAIL->imap->list_mailboxes() as $mbox)
        {
            // dont include junk or trash in the all folder search results
            if($mbox == $CONFIG['junk_mbox'] || $mbox == $CONFIG['trash_mbox'])
                continue;

            $RCMAIL->imap->set_mailbox($mbox);
            $RCMAIL->imap->search($mbox, $search_string, RCMAIL_CHARSET, $_SESSION['sort_col']);
            //$result = $RCMAIL->imap->list_headers($mbox, 1, $_SESSION['sort_col'], $_SESSION['sort_order']);
	    $result = $RCMAIL->imap->list_messages($mbox, 1, $_SESSION['sort_col'], $_SESSION['sort_order']);

            foreach($result as $row)
            {
                $uid_mboxes[$id] = array('uid' => $row->uid, 'mbox' => $mbox);

                $row->uid = $id;
                $result_h[] = $row;
                //array_push($result_h, $row);
                $id++;
            }
        }

        $_SESSION['all_folder_search']['uid_mboxes'] = $uid_mboxes;
        $RCMAIL->output->set_env('all_folder_search_uid_mboxes', $uid_mboxes);

        return $result_h;
    }

    /**
     * Get message header results for the current all folder search
     *
     * @return  array    Indexed array with message header objects
     * @access  public
     */
    function get_search_result()
    {
	global $RCMAIL;
        $result_h = array();

        foreach($_SESSION['all_folder_search']['uid_mboxes'] as $id => $uid_mbox)
        {
            $uid = $uid_mbox['uid'];
            $mbox = $uid_mbox['mbox'];

            // get the message headers directly, there is a caching issue with the imap wrapper
            $row = $RCMAIL->imap->get_headers($uid, $mbox);
            //$row = $RCMAIL->imap->iil_C_FetchHeader($RCMAIL->imap->conn, $mbox, $uid, TRUE, FALSE, $RCMAIL->imap->fetch_add_headers);
            $row->uid = $id;

            array_push($result_h, $row);
        }

        return $result_h;
    }

    /**
     * Slice message header array to return only the messages corresponding the page parameter
     *
     * @param   array    Indexed array with message header objects
     * @param   int      Current page to list
     * @return  array    Sliced array with message header objects
     * @access  public
     */
    function get_paged_result($result_h, $page)
    {
        global $RCMAIL;

        // Apply page size rules
        if(count($result_h) > $RCMAIL->imap->page_size)
            $result_h = array_slice($result_h, ($page-1)*$RCMAIL->imap->page_size, $RCMAIL->imap->page_size);

        return $result_h;
    }

    /**
     * Sort result header array by date, size, subject, or from using a bubble sort
     *
     * @param   array    Indexed array with message header objects
     * @access  public
     */
    function sort_search_result(&$result_h)
    {
        // Bubble sort! <3333 (ideally sorting and page trimming should be done
        // in js but php has the convienent rcmail_js_message_list function
        for($x = 0; $x < count($result_h); $x++)
        {
            for($y = 0; $y < count($result_h); $y++)
            {
                // ick can a variable name be put into a variable so i can get this out of 2 loops
                switch($_SESSION['sort_col'])
                {
                case 'date':    $first = $result_h[$x]->timestamp;
                                $second = $result_h[$y]->timestamp;
                                break;
                case 'size':    $first = $result_h[$x]->size;
                                $second = $result_h[$y]->size;
                                break;
                case 'subject': $first = strtolower($result_h[$x]->subject);
                                $second = strtolower($result_h[$y]->subject);
                                break;
                case 'from':    $first = strtolower($result_h[$x]->from);
                                $second = strtolower($result_h[$y]->from);
                                break;
                }

                if($first < $second)
                {
                    $hold = $result_h[$x];
                    $result_h[$x] = $result_h[$y];
                    $result_h[$y] = $hold;
                }
            }
        }

        if($_SESSION['sort_order'] == 'DESC')
            $result_h = array_reverse($result_h);
    }
}

?>