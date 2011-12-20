<?php
/**
 * Interest types used in Interests and Can help with.
 * You can add default types to your site by using a data decorator
 * with a requireDefaultRecords method that does something like:
 * public function requireDefaultRecords() {
 *      $options = array(
 *          array('Name' => 'Test', 'Title' => 'A Test'),
 *      );
 *      $this->owner->set_stat('default_records', $options);
 *  }
 *
 */
class BuddyInterests extends DataObject {
    public static $db = array(
        'Name' => 'varchar(50)',//Name
        'Title' => 'varchar(50)',//Title/Label
    );

    public static $belongs_many_many = array(
        'User' => 'Member',
    );

    public function requireDefaultRecords() {
        //For some reason in the decorator requireDefaultRecords
        //calls before the main requireDefaultRecords and trying to
        //call parent ends in a loop
        //Call requireDefaultRecords in extend - this should set the static
        $this->extend('requireDefaultRecords');
        parent::requireDefaultRecords();
    }

    /**
     * Gets Members that have set matching interests in declared interests table
     * NOTE: This will probably break if Member is in more than 1 group as matches will be wrong
     * @param int $curmemid
     * @param array $interestids
     * @param string $intjointable
     * @param string $anyall (0:any,1:all,2:only all)
     * @return DataObject | false if no matches
     */
    public static function search_matching(int $curmemid, array $interestids, $intjointable, $anyall = 0) {

        $intids = implode($interestids, ',');
        $intids = Convert::raw2sql($intids);

        $sqlQuery = new SQLQuery();
        if (DB::getConn()->getDatabaseServer() == 'mssql') {
            $sqlQuery->select = array(
                'Member.ID, Member.ClassName, Member.FirstName, Member.Surname,
                Member.BuddyPublicProfile, Member.AvatarID, Member.BuddyPublicAvatar',
                // IMPORTANT: Needs to be set after other selects to avoid overlays
                'COUNT(ints.MemberID) as Matching'
            );
        } else {
            $sqlQuery->select = array(
            'Member.*',
            // IMPORTANT: Needs to be set after other selects to avoid overlays
            'COUNT(ints.MemberID) as Matching'
            );
        }
        $sqlQuery->from = array(
          "Member",
        );
        //Get Budy info, so can check not already buddy or banned etc
        $sqlQuery->leftJoin('Buddy', "(Buddy.InitiatorID = $curmemid AND Buddy.BuddyID = Member.ID)
        OR (Buddy.BuddyID = $curmemid AND Buddy.InitiatorID = Member.ID)");
        //Join interests table to match any in our search
        $sqlQuery->innerJoin($intjointable, "ints.MemberID = Member.ID", 'ints');
        //Join groups and permissions to make sure user is allowed to be added as a buddy
        $sqlQuery->innerJoin('group_members', 'groups.MemberID = Member.ID', 'groups');
        $sqlQuery->innerJoin('permission', 'permission.GroupID = groups.GroupID');

        $sqlQuery->where = array(
            //"ints.BuddyInterestsID IN($intids)",//Has any interests
            "Member.ID != $curmemid",//Not current member
            "Buddy.Relationship IS NULL OR Buddy.Relationship = 2 OR Buddy.Relationship = 3",//No (or ended) relationship
            "permission.Code = 'ADD_AS_BUDDY'",//User can be added as a buddy
        );

        if ($anyall == 0) {
            $sqlQuery->where[] = "ints.BuddyInterestsID IN($intids)";//Has any of these interests
        }

        $sqlQuery->limit = self::get_static('BuddySearches', 'maxresults');
        //Group by to stop non results being returned as record because of Matching col
        if (DB::getConn()->getDatabaseServer() == 'mssql') {
            $sqlQuery->groupby('Member.ID, Member.ClassName, Member.FirstName, Member.Surname,
                Member.BuddyPublicProfile, Member.AvatarID, Member.BuddyPublicAvatar');
        } else {
            $sqlQuery->groupby('Member.ID');
        }

        if ($anyall != 0) {
            //Only get Members that have ALL interest ids listed
            //stackoverflow.com/questions/333750/fetching-only-rows-that-match-all-entries-in-a-joined-table-sql
            $having = "SUM(CASE WHEN COALESCE(ints.BuddyInterestsID, 0) IN ($intids)
                THEN 1 ELSE 0 END) = ". count($interestids);
            if ($anyall = 2) {
                //Make sure they only have selected interests e.g. num matches must = num searched for
                $having .= ' AND COUNT(*) = ' . count($interestids);
            }
            $sqlQuery->having($having);
        }

        $result = $sqlQuery->execute();
        if ($result->numRecords() != 0) {
            // Convert sql result to dataobjectset
            $myDataObjectSet = singleton('BuddyInterests')->buildDataObjectSet($result);
            return $myDataObjectSet;
        } else {
            return false;
        }
    }

}
