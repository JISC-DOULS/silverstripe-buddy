<div id="BuddyLayout" class="typography">
<h2><% _t('BUDDY.invitemessage','Invite message') %></h2>
<div id="buddies">
<% include BuddyMessage %>
<% if msg %>
<div id="buddyimsg">
<% control msg %>
<div class="msghead"><% _t('BUDDY.from', 'From') %>: $Initiator.getName</div>
<div class="msghead"><% _t('BUDDY.to', 'To') %>: $Buddy.getName</div>
<div class="msghead"><% _t('BUDDY.buddycreated', 'Date request made') %>: $Created.Nice</div>
<% end_control %>
<div class="msgbody">$content</div>
</div>
<% end_if %>
<p class="back"><a href="$Link"><% _t('Buddy.back', 'Back to Manage Buddies') %></a></p>
</div>
</div>