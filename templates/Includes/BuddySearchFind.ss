<%-- Search results --%>
<% if saveasearch %>
$saveasearch
<% end_if %>
<% if found %>
<p>
<% sprintf(_t('BUDDY.findresults','The search returned %s results.'),$found.Count) %>
</p>
<%-- invite - can't send params to form, so must make semi-manually in template --%>
<% control inviteForm %>
<form $FormAttributes >
<fieldset>
<div>
<table summary="List of users matching search criteria" class="buddyTable">
<thead>
<tr>
<th scope="col"><% _t('BUDDY.buddyavatar', 'Avatar') %></th>
<th scope="col"><% _t('BUDDY.buddyname', 'Name') %></th>
<th scope="col"><% _t('BUDDY.buddyup', 'Send invite') %></th>
</tr>
</thead>
<tbody>
<% control Top.found %>
<tr>
<td>
<%-- Avatar profile link --%>
    <% if BuddyPublicProfile %>
    <a href="$getProfileLink" title="$getName's profile">
    <% end_if %>
    $getBuddyAvatar
    <% if BuddyPublicProfile %>
    </a>
    <% end_if %>
</td>
<td>
$getName
</td>
<td class="buddyup">
  <input type="checkbox" name="$Top.inviteprefix$ID" value="$inviteHash" title="<% _t('BUDDY.buddyup', 'Send invite') %> to $getName"/>
</td>
</tr>
<% end_control %>
</tbody>
</table>
</div>
<div id="invitemsg">
<p><% _t('BUDDY.search_invitemsg', 'Invite message') %>:</p>
<% control Fields %>
$Field
<% end_control %>
</div>
<div>
<% control Actions %>
$Field
<% end_control %>
</div>
</fieldset>
</form>
<% end_control %>
<% else %>
<% _t('BUDDY.nofindresults', 'Sorry, no matching results were found') %>
<% end_if %>
<p class="back"><a href="$Link"><% _t('Buddy.backsearch', 'Back to Find a buddy') %></a></p>