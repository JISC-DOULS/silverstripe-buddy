<%-- Main layout template for Buddy search screens --%>
<div id="BuddyLayout" class="typography">
<h2><% _t('BUDDY.BuddySearches', 'Find a buddy') %></h2>
<div id="searches">
<% include BuddyMessage %>
<% if searches %>
    <% include BuddySavedSearches %>
<% end_if %>
<% if newform %>
    $newform
<% end_if %>
<% if isfind %>
    <% include BuddySearchFind %>
<% end_if %>
</div>
</div>