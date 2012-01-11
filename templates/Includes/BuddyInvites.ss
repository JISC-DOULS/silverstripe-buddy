<%-- Invites sent to user but not responded to --%>
<% if NotResponded %>
<p>Buddy requests you have yet to respond to:</p>
<table summary="List of Buddies invites outstanding" class="buddyTable">
<thead>
<tr>
<th scope="col"><% _t('BUDDY.buddyavatar', 'Avatar') %></th>
<th scope="col"><% _t('BUDDY.buddyfrom', 'From') %></th>
<th scope="col"><% _t('BUDDY.buddycreated', 'Date request made') %></th>
<th scope="col"><% _t('BUDDY.buddymessage', 'Summary of message') %></th>
<th scope="col"><% _t('BUDDY.buddyrespond', 'Respond') %></th>
</tr>
</thead>
<tbody>
<% control NotResponded %>
    <tr>
    <% control Initiator %>
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
    <td>$LastEdited.Nice</td>
    <td>
    <%-- summary --%>
    $getInviteSummary(50)
    <% if getInviteSummaryIsChopped(50) %>
     ...<div class='invitemsgmore'><a href="$Top.Link/viewimsg/$ID" title="<% _t('BUDDY.more', 'View all of message') %> from $Initiator.getName.XML"><% _t('BUDDY.more', 'View all of message') %></a></div>
    <% end_if %>
    </td>
    <td>
    <%-- respond - can't send params to form, so must make semi-manually in template --%>
        <% control Top.inviteActionForm %>
        <form $FormAttributes >
        <div class="invite_actions">
        <% control Actions %>
            $Field
        <% end_control %>
        </div>
        <div>
        <% control Fields %>
            $Field
        <% end_control %>
        <% end_control %>
        <input type='hidden' name='id' value="$ID"/></div>
        </form>
    </td>
    </tr>
</tbody>
</table>
<% end_control %>

<% end_if %>