<td>
    <% if getProfileLink %>
    <a href="$getProfileLink"  title="$getName.XML's profile">
    <% end_if %>
    $getBuddyAvatar
    <% if getProfileLink %>
    </a>
    <% end_if %>
    </td>
    <td>$getName.XML</td>
    <td>
    <% if getBuddyNewMessageLink %>
    <div class="new_message">
    <a href="$getBuddyNewMessageLink" rel="fb" title="<% _t('BUDDY.buddynewmessagelink', 'Send message') %> to $getName"><% _t('BUDDY.buddynewmessagelink', 'Send message') %></a>
    </div>
    <% else %>
    &nbsp;
    <% end_if %>
    </td>
    <td><a href="$getRemoveBuddylink" title="<% _t('BUDDY.unbuddy', 'Remove buddy') %> $getName"><% _t('BUDDY.unbuddy', 'Remove buddy') %></a></td>