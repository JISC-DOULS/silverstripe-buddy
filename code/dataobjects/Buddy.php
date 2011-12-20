<?php
/**
 *Data Object containing Buddy relationships
 */

class Buddy extends DataObject implements PermissionProvider{

    const RELATIONSHIP_INVITE = 0;
    const RELATIONSHIP_CONFIRMED = 1;
    const RELATIONSHIP_REJECTED = 2;
    const RELATIONSHIP_ENDED = 3;
    const RELATIONSHIP_BANNED = 4;

    public static $db = array(
        'Relationship' => 'Int',
        'InviteMessage' => 'Text',
    );

    public static $has_one = array(
        'Initiator' => 'Member',
        'Buddy' => 'Member',
    );

    public static $defaults = array(
        'Relationship' => self::RELATIONSHIP_INVITE,
    );

    public static $summary_fields = array(
        'Relationship',
        'InviteMessage',
        'InitiatorID',
        'InitiatorName',
        'BuddyID',
        'BuddyName'
    );

    /**
     * To stop errors in CMS searchable fields must be set
     * so custom functions added to summary fields are ignored
     */
    public static $searchable_fields = array(
           'Relationship',
        'InviteMessage',
        'InitiatorID',
        'BuddyID'
    );

    /**
     * In CMS summary fields show name of member
     */
    public function getInitiatorName() {
        return $this->getComponent('Initiator')->getName();
    }
    /**
     * In CMS summary fields show name of member
     */
    public function getBuddyName() {
        return $this->getComponent('Buddy')->getName();
    }


    public function providePermissions() {
        return array(
        //Member needs this to be able to invite someone as a buddy (can still be invited)
            'ADD_A_BUDDY' => array(
                'name' => _t('BUDDY.ADD_A_BUDDY', 'Invite another member as a buddy'),
                'category' => _t('BUDDY.BuddyCategory', 'Buddy module'),
                'help' => _t('BUDDY.ADD_A_BUDDY_HELP', 'Users with this permission can invite other
                 members to be their buddy'),
                'sort' => 400
        ),
        //Member needs this to receive invitations (and be included in search)
            'ADD_AS_BUDDY' => array(
                'name' => _t('BUDDY.ADD_AS_BUDDY', 'Can be invited to be a buddy'),
                'category' => _t('BUUDY.BuddyCategory', 'Buddy module'),
                'help' => _t('BUDDY.ADD_AS_BUDDY_HELP', 'Users with this permission can be invited by
                 other members to be their buddy. They will not appear on the search page if they do
                 not have this permission.'),
                'sort' => 400
        ),
            //Member needs this to be able to view profiles (doesn't override other user setting)
            'VIEW_BUDDY_PROFILE' => array(
                'name' => _t('BUDDY.VIEW_BUDDY_PROFILE', 'Can view others profiles'),
                'category' => _t('BUUDY.BuddyCategory', 'Buddy module'),
                'help' => _t('BUDDY.VIEW_BUDDY_PROFILE_HELP', 'Users with this permission can
                 view other members profiles. This will only be the case if the other user has
                 let them view their profile.'),
                'sort' => 400
        ),
        );
    }

    /* PUBLIC FUNCTIONS TO INTERACT WITH DATA IN TEMPLATES */
    /**
     * Get the invite message for the current record and returrn a summary
     * @param $maxchars Number of characters to return
     * @param $test Test if the string returned will be chopped
     * @return String
     */
    public function getInviteSummary($maxchars = 50, $test = false) {
        $content = $this->InviteMessage;
        //$content = str_replace(array('\\r', '\\n'), array("\r", "\n"), $content);
        $content = Convert::raw2xml($content);
        $content = stripslashes($content);
        if ($test) {
            //Return whether the string will be chopped
            if (mb_strlen($content) > $maxchars) {
                return true;
            } else {
                return false;
            }
        }
        return mb_substr($content, 0 ,$maxchars);
    }

    /**
     * Check if the Invite message for the current record will be chopped when shown in a summary
     * @param $maxchars
     * @return Boolean
     */
    public function getInviteSummaryIsChopped($maxchars = 50) {
        return $this->getInviteSummary($maxchars, true);
    }

    /* STATIC FUNCTIONS TO GET DATA FROM IN CONTROLLER ETC */

    /**
     * Gets all of the members buddy records from table for relationship type
     * @param int $memberID
     * @return DataObjectSet
     */
    public static function getMemberBuddies(int $memberID, $total = null, $start = 0,
        $relationship = self::RELATIONSHIP_CONFIRMED) {
        $limit = '';
        if ($total) {
            $limit = "$start,$total";
        }
        //Joins are added to the member table so we can sort by the correct name
        /*$buddies = DataObject::get('Buddy',
            "(InitiatorID = {$memberID} OR BuddyID = {$memberID}) AND Relationship = {$relationship}",
            "CASE WHEN InitiatorID = {$memberID} THEN IsBuddy.FirstName ELSE IsInitiator.FirstName END",
            "LEFT JOIN Member AS IsBuddy ON BuddyID = IsBuddy.ID AND InitiatorID = {$memberID}
            LEFT JOIN Member AS IsInitiator ON InitiatorID = IsInitiator.ID AND BuddyID = {$memberID}",
            $limit
        );
        return $buddies;*/
        //Sql query version to work on MSSQL as get ver doesn't
        $sqlQuery = new SQLQuery();
        $sqlQuery->select = array('Buddy.*');
        $sqlQuery->from = array('Buddy');
        $sqlQuery->where = array(
              "(InitiatorID = {$memberID} OR BuddyID = {$memberID}) AND Relationship = {$relationship}"
        );
        $sqlQuery->leftJoin('Member', "BuddyID = IsBuddy.ID AND InitiatorID = {$memberID}", 'IsBuddy');
        $sqlQuery->leftJoin('Member', "InitiatorID = IsInitiator.ID AND BuddyID = {$memberID}", 'IsInitiator');
        $sqlQuery->orderby("CASE WHEN InitiatorID = {$memberID} THEN IsBuddy.FirstName ELSE IsInitiator.FirstName END");
        $sqlQuery->limit($limit);
        $result = $sqlQuery->execute();
        if ($result->numRecords() != 0) {
            // Convert sql result to dataobjectset
            $myDataObjectSet = singleton('Buddy')->buildDataObjectSet($result);
            $myDataObjectSet->parseQueryLimit($sqlQuery);
            return $myDataObjectSet;
        } else {
            return new DataObjectSet();
        }
    }

    /**
     * Return number of confirmed buddies that the member has
     * (In theory quicker than counting getMemberBuddies)
     * @param int $memberID
     * @return int
     */
    public static function getMemberNumberBuddies(int $memberID) {
        $relationship = self::RELATIONSHIP_CONFIRMED;
        $sqlQuery = new SQLQuery();
        $sqlQuery->select = array('COUNT(Buddy.ID)');
        $sqlQuery->from = array('Buddy');
        $sqlQuery->where = array(
              "InitiatorID = {$memberID} OR BuddyID = {$memberID} AND Relationship = {$relationship}"
        );
        return $sqlQuery->execute()->value();
    }

    /**
     * Find a relationship (any) between 2 members
     * @param int $member1
     * @param int $member2
     * @return DataObject
     */
    public static function getAreBuddies(int $member1, int $member2, $relationship = null) {
        $filterr = '';
        if ($relationship !== null) {
            $filterr = 'AND Relationship = ' . intval($relationship);
        }
        return DataObject::get_one('Buddy',
            "((InitiatorID = {$member1} AND BuddyID = {$member2})
            OR (InitiatorID = {$member2} AND BuddyID = {$member1})) $filterr");
    }

    /**
     * Ends a budy relationship
     * @param int $member1
     * @param int $member2
     * @return DataObject
     */
    public static function deleteBuddy(int $member1, int $member2) {
        $relationship = self::RELATIONSHIP_CONFIRMED;
        $result =  DataObject::get_one('Buddy',
            "(InitiatorID = {$member1} AND BuddyID = {$member2})
            OR (InitiatorID = {$member2} AND BuddyID = {$member1}) AND Relationship = $relationship");
        if ($result) {
            $result->Relationship = self::RELATIONSHIP_ENDED;
            $result->write();
        } else {
            throw new Exception('Relationship does not exist');
        }
    }

    /**
     * Returns all invites a user has made that have yet to of been responded to
     * @param int $memberID
     * @return DataObject
     */
    public static function getMemberNoResponds(int $memberID) {
        $relationship = self::RELATIONSHIP_INVITE;
        //Joins are added to the member table so we can sort by the correct name
        $buddies = DataObject::get('Buddy',
            "InitiatorID = {$memberID} AND Relationship = {$relationship}",
            "Created DESC"
        );
        return $buddies;
    }

    /**
     * Returns all invites a user has yet to respond to
     * @param int $memberID
     * @return DataObject
     */
    public static function getMemberToResponds(int $memberID) {
        $relationship = self::RELATIONSHIP_INVITE;
        //Joins are added to the member table so we can sort by the correct name
        $buddies = DataObject::get('Buddy',
            "BuddyID = {$memberID} AND Relationship = {$relationship}",
            "Created DESC"
        );
        return $buddies;
    }

    /**
     * Return a specific message
     * The message must be valid (so still an invite and for/by member)
     * @param int $id
     * @param int $memberID
     * @return DataObject
     */
    public static function getAInviteMessage(int $id, int $memberID) {
        $relationship = self::RELATIONSHIP_INVITE;
        $msgrec = DataObject::get_one('Buddy',
            "ID = $id AND Relationship = {$relationship}
            AND (InitiatorID = $memberID OR BuddyID = $memberID)");
        return $msgrec;
    }

    /**
     * Updates a buddy record invite for a invitee
     * @param int $id Record id
     * @param int $buddyID Buddy ID (used to double check allowed)
     * @param int $newstatus New relationship status
     */
    public static function updateBuddyInvite(int $id, int $buddyID, int $newstatus) {
        $relationship = self::RELATIONSHIP_INVITE;
        $result =  DataObject::get_one('Buddy',
            "ID = {$id}
            AND BuddyID = {$buddyID}
            AND Relationship = $relationship");
        if ($result) {
            $result->Relationship = $newstatus;
            $result->write();
            return $result;
        } else {
            return false;
        }
    }

    /**
     * Creates/Updates a buddy relationship invite
     * Heavy checking of users and existing relationship
     * @param int $buddy
     * @param string $message
     * @return boolean success
     */
    public static function inviteRequest(int $buddy, $message = '') {
        $message = strip_tags($message);
        $initiator = Member::currentUserID();
        //First, check for existing, if applicable - update else fail
        $existing = self::getAreBuddies($initiator, $buddy);
        if ($existing) {
            //Only allow to recreate relationship in certain circumstances
            if ($existing->Relationship == self::RELATIONSHIP_ENDED ||
                    $existing->Relationship == self::RELATIONSHIP_REJECTED) {
                $existing->Relationship = self::RELATIONSHIP_INVITE;
                $existing->InviteMessage = $message;
                $existing->write();
                return true;
            } else {
                return false;
            }
        }
        //Second, check buddy is a valid member (exists and has permission)
        if ($amember = DataObject::get_by_id('Member', $buddy)) {
            if (Permission::checkMember($amember, 'ADD_AS_BUDDY')) {
                $params = array();
                $params['Relationship'] = self::RELATIONSHIP_INVITE;
                $params['InitiatorID'] = $initiator;
                $params['BuddyID'] = $buddy;
                $params['InviteMessage'] = $message;
                $rel = new Buddy($params);
                $rel->write();
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
}
