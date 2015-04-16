# Introduction #

The logic on how roundcube should react to the selection and moving of multiple messages that span multiple mailboxes is unclear.

# Details #

Okay so I've been thinking more about handling single message dragging/deleting...

Removing a message is annoying because it selects the next message in the list before it actually removes the last message and when it selects the next message in the list, it also changes the mbox which changes the folder that the message is moved from.

So here is the final plan:

Deleting will work like it does in roundcube, if it isn't in the trash folder, it will be, if it is, it will be deleted permanently (which requires the select\_next issue to be sorted out)

Since deleting messages just moves them to another mbox (trash) the messages will remain in the all folder search which may be confusing since one might expect a visual change so a detailed message should pop up explaining what happened

... and now multiple message dragging/deleting

Deleting will be simple, a message will come up saying how many messages were sent to trash and how many were permanently deleting (if any)

Moving messages...

I don't really see a perfect solution to this one but I think it would be best to simply have 2 messages come up, one successful, saying how many messages were moved, one saying some messages already belong to the target mbox