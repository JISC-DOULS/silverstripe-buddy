<%-- Buddy profile edit/view --%>
<div id="BuddyLayout" class="typography">
<h2><% _t('BUDDY.Profile','Profile') %></h2>
<div id="profile">
<% include BuddyMessage %>
<% if form %>
<%-- Edit profile form --%>
$form
<% else_if pdata %>
<%-- Show profile --%>
<% control pdata %>
<div class="$type">
<div class="name">$name: </div>
<div class="content">
<% if type = "default" %>
    $value
<% else_if type = "ManyManyCheckboxSet" %>
    <% if value %>
    <ul>
    <% control value %>
    <li>$Title</li>
    <% end_control %>
    </ul>
    <% end_if %>
<% end_if %>
</div>
</div>
<% end_control %>
<% end_if %>
</div>
</div>