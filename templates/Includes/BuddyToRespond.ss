<%-- Buddy invites sent but not responded to --%>
<% if NoResponses %>
<p>Your Buddy requests yet to be responded to:</p>
<table summary="List of Buddies requests outstanding" class="buddyTable">
<thead>
<tr>
<th scope="col"><% _t('BUDDY.buddyavatar', 'Avatar') %></th>
<th scope="col"><% _t('BUDDY.buddyto', 'To') %></th>
<th scope="col"><% _t('BUDDY.buddycreated', 'Date request made') %></th>
<th scope="col"><% _t('BUDDY.buddymessage', 'Summary of message') %></th>
</tr>
</thead>
<tbody>
<% control NoResponses %>
    <tr>
    <% control Buddy %>
    <td>
    <% if BuddyPublicProfile %>
    <a href="$getProfileLink" title="$getName.XML's profile">
    <% end_if %>
    $getBuddyAvatar
    <% if BuddyPublicProfile %>
    </a>
    <% end_if %>
    </td>
    <td>$getName.XML</td>
    <% end_control %>
    <td>$Created.Nice</td>
    <td>
    $getInviteSummary(50)
    <% if getInviteSummaryIsChopped(50) %>
     ...<div class='invitemsgmore'><a href="$Top.Link/viewimsg/$ID" title="<% _t('BUDDY.more', 'View all of message') %> to $Buddy.getName.XML"><% _t('BUDDY.more', 'View all of message') %></a></div>
    <% end_if %>
    </td>
    </tr>
<% end_control %>
</tbody>
</table>
<% end_if %>