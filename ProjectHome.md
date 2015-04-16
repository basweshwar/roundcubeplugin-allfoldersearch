Update coming soon that will get rid of the need for all of these additional hooks!

## Additional hooks needed for this plugin ##

### program/js/app.js ###
need hooks at the beginning and end of `msglist_select`
```
...

  this.msglist_select = function(list)
    {
    // trigger hook
    this.triggerEvent('pre_msglist_select', { list:list });
    
    if (this.preview_timer)
      clearTimeout(this.preview_timer);

...

    else if (this.env.contentframe)
      this.show_contentframe(false);
    
    // trigger hook
    this.triggerEvent('post_msglist_select', { list:list });
    };

...
```

### index.php ###
simply need to pass along `$_GET` to the existing startup hook and set add `$_GET = $startup['get'];`
```
...
  exit;
}

// trigger startup plugin hook
$startup = $RCMAIL->plugins->exec_hook('startup', array('task' => $RCMAIL->task, 'action' => $RCMAIL->action, 'get' => $_GET));
$RCMAIL->set_task($startup['task']);
$RCMAIL->action = $startup['action'];
$_GET = $startup['get'];

...
```

### program/steps/mail/search.inc ###
```
...

// allow plugins to override the search
$plugin_search_override_data = rcmail::get_instance()->plugins->exec_hook('search_override',array());
if($plugin_search_override_data['abort']) exit;

$REMOTE_REQUEST = TRUE;

...
```

### program/steps/mail/list.inc ###
```
...

if (!$OUTPUT->ajax_call) {
  return;
}

// allow plugins to override the search
$plugin_list_override_data = rcmail::get_instance()->plugins->exec_hook('list_override', array());
if($plugin_list_override_data['abort']) exit;

// is there a sort type for this request?
if ($sort = get_input_value('_sort', RCUBE_INPUT_GET))

...
```

### program/steps/mail/mark.inc ###
```
...

  $flag = $a_flags_map[$flag] ? $a_flags_map[$flag] : strtoupper($flag);

  // allow plugins to override the search
  $plugin_mark_override_data = rcmail::get_instance()->plugins->exec_hook('mark_override',array('uids'=>$uids, 'flag'=>$flag));
  if($plugin_mark_override_data['abort']) exit;
  
  if ($flag == 'DELETED' && $CONFIG['skip_deleted'] && $_POST['_from'] != 'show') {

...
```