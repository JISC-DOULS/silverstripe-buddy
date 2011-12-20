<?php
/**
 * Extension to TagField Module class for the studying and studied field
 * Standard function is to work as tag cloud
 * AJAX action is directed to either search or profile page as required
 * Extension this in mysite class and add altsuggest method to return alt autosuggest results
 */
class StudyChoiceField extends TagField {

    /**
     * Exact duplicate of standard Tag Field
     * Exception that href for ajax requests is set explicitly to known controllers
     * And local css
     */
    public function Field() {
        Requirements::javascript(THIRDPARTY_DIR . "/jquery/jquery.js");
        Requirements::javascript(SAPPHIRE_DIR . "/javascript/jquery_improvements.js");

        // If the request was made via AJAX, we need livequery to init the field.
        if (Director::is_ajax()) {
            Requirements::javascript(THIRDPARTY_DIR . '/jquery-livequery/jquery.livequery.js');
        }

        Requirements::javascript("tagfield/thirdparty/jquery-tags/jquery.tags.js");
        Requirements::javascript("tagfield/javascript/TagField.js");
        Requirements::css("buddy/css/TagField.css");

        // Standard textfield stuff
        $attributes = array(
            'type' => 'text',
            'class' => 'text tagField',
            'id' => $this->id(),
            'name' => $this->Name(),
            'value' => $this->Value(),
            'tabindex' => $this->getTabIndex(),
            'autocomplete' => 'off',
            'maxlength' => ($this->maxLength) ? $this->maxLength : null,
            'size' => ($this->maxLength) ? min( $this->maxLength, 30 ) : null,
        );
        if($this->disabled) $attributes['disabled'] = 'disabled';

        // Data passed as custom attributes
        if($this->customTags) {
            $attributes['tags'] = $this->customTags;
        } else {
            if ($this->Name() == 'study') {
                //On Searches page controller
                $attributes['href'] = BuddySearches::get_static('BuddySearches', 'url_segment')
                    . '/suggest';
            } else {
                //On profile page
                $attributes['href'] = BuddyProfile::get_static('BuddyProfile', 'url_segment')
                    . '/suggest/' . $this->Name() . '/';
            }
        }
        $attributes['rel'] = $this->separator;

        return $this->createTag('input', $attributes);
    }

    /**
     * Calls standard suggest method - or altsuggest if available (add via extension)
     * @param $request
     */
    public function suggest($request) {
        if ($this->hasMethod('altsuggest')) {
            return $this->altsuggest();
        } else {
            return strip_tags(parent::suggest($request));
        }
    }

    /**
     * Special suggest to get results for both buddy study member fields
     * altsuggest will be called instead if available (add via extension)
     */
    public function combinedsuggest($request) {
        if ($this->hasMethod('altsuggest')) {
            return $this->altsuggest();
        } else {
            $tagTopicClassObj = singleton($this->getTagTopicClass());

            $searchString = $request->requestVar('tag');
            $this->setName('BuddyStudying');
            $tags = $this->getTextbasedTags($searchString);
            $this->setName('BuddyStudied');
            $tags2 = $this->getTextbasedTags($searchString);

            $tags = array_merge($tags, $tags2);
            $tags = array_unique($tags);
            return strip_tags(Convert::raw2json($tags));
        }
    }

    /**
     * Gets all members that have any of the search terms in member field
     * Members must not already be a buddy or selected member
     * And must be able to be added as a buddy
     * Uses sub queries and union all to get result for each matching word
     * These are then counted and given a weighting of 2
     * @param int $curmemid Current member
     * @param string $field field to look in
     * @param string $search search term(s) to match
     */
    public static function get_matching_members(int $curmemid, $field, $search) {
        $sqlQuery = new SQLQuery();

        if (DB::getConn()->getDatabaseServer() == 'mssql') {
            $sqlQuery->select = array(
                'Member.ID, Member.ClassName, Member.FirstName, Member.Surname,
                Member.BuddyPublicProfile, Member.AvatarID, Member.BuddyPublicAvatar',
                // IMPORTANT: Needs to be set after other selects to avoid overlays
                'COUNT(Member.ID) * 2 as Matching'
            );
        } else {
            $sqlQuery->select = array(
                'Member.*',
                // IMPORTANT: Needs to be set after other selects to avoid overlays
                'COUNT(Member.ID) * 2 as Matching'
            );
        }
        //Setup FROM - This is multiselect based on search terms
        $from = '(';
        $searcharr = explode(' ', trim($search));
        $len = Count($searcharr) > 10 ? 10 : Count($searcharr);
        $limit = self::get_static('BuddySearches', 'maxresults');
        for ($a = 0; $a < $len; $a++) {
            $value = Convert::raw2sql($searcharr[$a]);
            if ($a > 0) {
                $from .= ' UNION ALL ';
            }
            if (DB::getConn()->getDatabaseServer() == 'mssql') {
                $from .= "(SELECT TOP $limit * FROM Member WHERE (LOWER(Member.$field) LIKE LOWER('%$value%')))";
            } else {
                $from .= "(SELECT * FROM Member WHERE (LOWER(Member.$field) LIKE LOWER('%$value%'))
                LIMIT $limit)";
            }
        }
        $from .= ') as Member';
        $sqlQuery->from = array(
          $from
        );
        //Get Budy info, so can check not already buddy or banned etc
        $sqlQuery->leftJoin('Buddy', "(Buddy.InitiatorID = $curmemid AND Buddy.BuddyID = Member.ID)
        OR (Buddy.BuddyID = $curmemid AND Buddy.InitiatorID = Member.ID)");
        //Join groups and permissions to make sure user is allowed to be added as a buddy
        $sqlQuery->innerJoin('group_members', 'groups.MemberID = Member.ID', 'groups');
        $sqlQuery->innerJoin('permission', 'permission.GroupID = groups.GroupID');

        $sqlQuery->where = array(
            //"ints.BuddyInterestsID IN($intids)",//Has any interests
            "Member.ID != $curmemid",//Not current member
            "Buddy.Relationship IS NULL OR Buddy.Relationship = 2 OR Buddy.Relationship = 3",//No (or ended) relationship
            "permission.Code = 'ADD_AS_BUDDY'",//User can be added as a buddy
        );

        $sqlQuery->limit = self::get_static('BuddySearches', 'maxresults');
        //Group by to stop non results being returned as record because of Matching col
        if (DB::getConn()->getDatabaseServer() == 'mssql') {
            $sqlQuery->groupby('Member.ID, Member.ClassName, Member.FirstName, Member.Surname,
                Member.BuddyPublicProfile, Member.AvatarID, Member.BuddyPublicAvatar');
        } else {
            $sqlQuery->groupby('Member.ID');
        }

        $result = $sqlQuery->execute();
        if ($result->numRecords() != 0) {
            // Convert sql result to dataobjectset
            $myDataObjectSet = singleton('Member')->buildDataObjectSet($result);
            return $myDataObjectSet;
        } else {
            return false;
        }
    }
}
