<div id="BuddyLayout" class="typography">
<h2><% _t('BUDDY.Buddies','Manage Buddies') %></h2>
<div id="buddies">
<% include BuddyMessage %>
<% if delete %>
$delete
<% end_if %>
</div>
</div>