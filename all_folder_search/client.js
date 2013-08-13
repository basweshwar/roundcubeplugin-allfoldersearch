/* notes
 *
 * if select next becomes a problem just replace the prototype in list.js
 *
 */

if (window.rcmail) {

    rcmail.addEventListener('init', function(evt) {

        // append all folder search option to the search menu

        var itemInput = $('<INPUT>');
        itemInput.attr('id', 's_mod_all_folder_search')
        itemInput.attr('type', 'checkbox');
        itemInput.attr('onclick', 'rcmail_ui.set_searchmod(this)')
        itemInput.attr('value', 'all_folder_search');
        itemInput.attr('name', 's_mods[]');

        var itemLabel = $('<LABEL>');
        itemLabel.attr('for', 's_mod_all_folder_search');
        itemLabel.html('All Folders');

        var item = $('<LI>').append(itemInput)
        item.append(itemLabel);

        $('#searchmenu ul').append(item);

    });

    rcmail.addEventListener('beforelist', function(evt) {

        // this is how we will know we are in an all folder search
        if(evt != '' && rcmail.env.all_folder_search_active)
        {
            rcmail.env.all_folder_search_active = 0;
            rcmail.env.search_request = null;
            rcmail.env.all_folder_search_uid_mboxes = null;
            rcmail.http_post('plugin.not_all_folder_search', true);
        }

    });

    rcmail.addEventListener('pre_msglist_select', function(evt) {

        if(rcmail.env.all_folder_search_active)
        {
            if(evt.list.selection.length == 1)
            {
                var mbox = rcmail.env.all_folder_search_uid_mboxes[evt.list.selection[0]].mbox;
                var uid = rcmail.env.all_folder_search_uid_mboxes[evt.list.selection[0]].uid;
                rcmail.select_folder(mbox, rcmail.env.mailbox);
                rcmail.env.mailbox = mbox;
                rcmail.env.uid= uid;
            }

            rcmail.message_list.draggable = false;
        }
        else
            rcmail.message_list.draggable = true;

    });

    rcmail.addEventListener('post_msglist_select', function(evt) {

        rcmail.enable_command('delete', rcmail.env.all_folder_search_active ? false : true);

    });

}