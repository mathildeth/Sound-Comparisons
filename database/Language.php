<?php
require_once 'Translatable.php';
/**
  Corresponds to an Entry from the Languages table.
*/
class Language extends Translatable{
  //Inherited from Translatable:
  protected static function getTranslationPrefix(){
    return 'LanguagesTranslationProvider-Languages_-Trans_';
  }
  /***/
  protected function buildSelectQuery($fs){
    $sid = $this->getValueManager()->getStudy()->getId();
    $id  = $this->id;
    return "SELECT $fs FROM Languages_$sid WHERE LanguageIx = $id";
  }
  /***/
  protected function findKey(){
    if($r = $this->fetchFields('ShortName')){
      $this->key = $r[0];
      //Checking if we depend on LanguageStatusType:
      $sid = $this->getValueManager()->getStudy()->getId();
      $q   = "SELECT COUNT(ShortName) FROM Languages_$sid WHERE ShortName = '".$r[0]."'";
      if($r = $this->fetchOneBy($q)){
        if($r[0] > 1){
          $r = $this->fetchFields('LanguageStatusType');
          if(!empty($r[0]) && $r[0] !== '0'){
            $q = "SELECT Status FROM LanguageStatusTypes WHERE LanguageStatusType = ".$r[0];
            $type = current($this->fetchOneBy($q));
            $this->key .= "<$type>";
          }
        }
      }
    }else Config::error("database/Language.php: No name for Language: ".$this->id);
  }
  /**
    @return [$skey] String
    Produces a key that let's us sort Languages by their regionGroups.
  */
  public function getSortKey(){
    $sid = $this->getValueManager()->getStudy()->getId();
    $id = $this->id;
    $q = "SELECT CONCAT(StudyIx, FamilyIx, SubFamilyIx, RegionGpIx, $id) "
       . "FROM RegionLanguages_$sid WHERE LanguageIx = $id";
    if($r = $this->fetchOneBy($q))
      return $r[0];
    Config::error("Fail in database/Language->getSortKey() for LanguageIx $id");
  }
  /**
    @param [$superscript=true] Bool
    @return $shortName String
    The name of a language ready to display,
    translated if possible.
    If $superscript is true, the $shortName is returned
    as HTML with a superscript that characterises the Language.
  */
  public function getShortName($superscript = true){
    Stopwatch::start('Language:getShortName');
    $id   = $this->id;
    $sid  = $this->getValueManager()->getStudy()->getId();
    $name = $this->getKey(); // Fallback if no ShortName is found
    //Fetchig the ShortName field:
    if($r = $this->fetchFields('ShortName'))
      $name = $r[0];
    //Fetching a translation if possible:
    if($trans = $this->getValueManager()->getTranslator()->dt($this)){
      foreach(array('RegionGpMemberLgNameShortInThisSubFamilyWebsite'
                   ,'RegionGpMemberLgNameLongInThisSubFamilyWebsite'
                   ,'ShortName'
                   ) as $k){
        if(array_key_exists($k, $trans)){
          $v = $trans[$k];
          if($v != ''){
            $name = $v;
            break;
          }
        }
      }
    }
    //Fetching the superscript if requested:
    if($superscript)
      $name = $this->getSuperscript($name);
    Stopwatch::stop('Language:getShortName');
    return $name;
  }
  /**
    @param [$superscript=true] Bool
    @return $longName String
    Works like getShortName except that the $longName is richer on details.
  */
  public function getLongName($superscript = true){
    //Try to find a translation first:
    if($trans = $this->getValueManager()->getTranslator()->dt($this)){
      $k = 'RegionGpMemberLgNameLongInThisSubFamilyWebsite';
      if(array_key_exists($k, $trans)){
        $trans = $trans[$k];
        if($trans !== ''){
          if($superscript) $trans = $this->getSuperscript($trans);
          return $trans;
        }
      }
    }//Try to find a long name:
    $sid = $this->getValueManager()->getStudy()->getId();
    $id  = $this->id;
    $q = "SELECT RegionGpMemberLgNameLongInThisSubFamilyWebsite FROM RegionLanguages_$sid "
       . "WHERE RegionGpMemberLgNameLongInThisSubFamilyWebsite IS NOT NULL "
       . "AND RegionGpMemberLgNameLongInThisSubFamilyWebsite != '' "
       . "AND LanguageIx = $id";
    if($r = Config::getConnection()->query($q)->fetch_row()){
      $lname = $r[0];
      if($superscript)
        $lname = $this->getSuperscript($lname);
      return $lname;
    }//Fallback on shortName:
    return $this->getShortName($superscript);
  }
  /**
    @return $spellingName String
    Returns the SpellingRfcLangName from the Languages table.
  */
  public function getSpellingName(){
    if($t = $this->getValueManager()->getTranslator()->dt($this)){
      if(array_key_exists('SpellingRfcLangName', $t)){
        $t = $t['SpellingRfcLangName'];
        if($t != '') return $t;
      }
    }
    if($r = $this->fetchFields('SpellingRfcLangName')){
      if($r[0]) return $r[0];
    }
    return $this->getShortName(false);
  }
  /**
    @param [$target] String that the superscript belongs to.
    @return superscript String - a HTML div element.
    Generates a superscript that can be attached to a given target,
    or may be used as 'stand alone' if $target is null.
  */
  public function getSuperscript($target = null){
    $s    = '';
    $ttip = '';
    $sid  = $this->getValueManager()->getStudy()->getId();
    $id   = $this->id;
    $q    = "SELECT Status, StatusTooltip FROM LanguageStatusTypes "
          . "WHERE LanguageStatusType = (SELECT LanguageStatusType "
          . "FROM Languages_$sid WHERE LanguageIx = $id) "
          . "AND Status IS NOT NULL AND StatusTooltip IS NOT NULL";
    if($r = $this->fetchOneBy($q)){
      $s    = $r[0];
      $ttip = $r[1];
    }
    if($trans = $this->getValueManager()->getTranslator()->getLanguageStatusTypeTranslation($this)){
      if($trans[0] != ''){
        $s = $trans[0];
      }
      $ttip = $trans[2];
    }
    return array(
      'target'      => $target
    , 'ttip'        => $ttip
    , 'superscript' => $s
    , 'isSuper'     => true
    );
  }
  /**
    @return [$languageStatusType] String
    Returns the LanguageStatusType for a Language.
  */
  public function getLanguageStatusType(){
    if($r = $this->fetchFields('LanguageStatusType'))
      return $r[0];
    return null;
  }
  /**
    @return color String of the form '#xxxxxx' with x being hexadecimal.
    The Languages color can either be determined by the Study it belongs to,
    or by the LanguageStatusType that it has.
    If neither is found in the database,
    the std. background color of the website is returned.
  */
  public function getColor(){
    if($this->getValueManager()->getStudy()->getColorByFamily()){
      $compare = $this->buildSelectQuery('CONCAT(StudyIx, FamilyIx)');
      $q = "SELECT FamilyColorOnWebsite FROM Families WHERE CONCAT(StudyIx, FamilyIx) = ($compare)";
    }else{
      $compare = $this->buildSelectQuery('LanguageStatusType');
      $q = "SELECT Color FROM LanguageStatusTypes WHERE LanguageStatusType = ($compare)";
    }
    if($r = $this->fetchOneBy($q))
      return '#'.$r[0];
    return '#FFFACD';
  }
  /***/
  public function getOpacity(){
    $compare = $this->buildSelectQuery('LanguageStatusType');
    $q = "SELECT Opacity FROM LanguageStatusTypes WHERE LanguageStatusType = ($compare)";
    return $this->fetchOneBy($q);
  }
  /***/
  public function getColorDepth(){
    $compare = $this->buildSelectQuery('LanguageStatusType');
    $q = "SELECT ColorDepth FROM LanguageStatusTypes WHERE LanguageStatusType = ($compare)";
    return $this->fetchOneBy($q);
  }
  /**
    @return [$rfcLanguage] Language
    Fetches the RfcLanguage for a Language if it exists.
  */
  public function getRfcLanguage(){
    $sid = $this->getValueManager()->getStudy()->getId();
    $id  = $this->id;
    $q = "SELECT RfcLanguage FROM Languages_$sid "
       . "WHERE LanguageIx = $id "
       . "AND RfcLanguage = ANY (SELECT LanguageIx FROM Languages_$sid) "
       . "AND RfcLanguage != $id"; // No identities
    if($r = $this->fetchOneBy($q))
      return new LanguageFromId($this->v, $r[0]);
    return null;
  }
  /**
    @return is Bool
    Tells, if the Language is a ReferenceLanguage.
  */
  public function isRfcLanguage(){
    $sKey = $this->getValueManager()->getStudy()->getKey();
    $id = $this->getId();
    $q = "SELECT COUNT(*) FROM Languages_$sKey WHERE RfcLanguage = $id AND LanguageIx != $id";
    if($r = $this->fetchOneBy($q))
      return ($r[0] > 0);
    return false;
  }
  /**
    @return $path String
    Tries to select the Language part of a soundfile path.
    If it fails php dies, because that would be a heavy error.
  */
  public function getPath(){
    if($r = $this->fetchFields('FilePathPart'))
      return $r[0];
    else Config::error('No CcdOverallFilename for LanguageId: '.$this->id);
  }
  /**
    @return $regions Region[]
    Returns all Regions a Language belongs to.
  */
  public function getRegions(){
    Stopwatch::start('Language:getRegions');
    $sid = $this->getValueManager()->getStudy()->getId();
    $id = $this->id;
    $q = "SELECT CONCAT(StudyIx, FamilyIX, SubFamilyIx, RegionGpIx) "
       . "FROM RegionLanguages_$sid WHERE LanguageIx = $id";
    $set = Config::getConnection()->query($q);
    $ret = array();
    while($row = $set->fetch_row())
      array_push($ret, new RegionFromId($this->v, $row[0]));
    Stopwatch::stop('Language:getRegions');
    return $ret;
  }
  /**
    @return $region Region
    Fetches only the first Region from this Language
  */
  public function getRegion(){
    $regions = $this->getRegions();
    return array_shift($regions);
  }
  /**
    @param $region Region
    @return $belongs Bool
    Checks if a Language belongs to a given Region.
  */
  public function belongsToRegion($region){
    Stopwatch::start('Language:belongsToRegion');
    $sid = $this->getValueManager()->getStudy()->getId();
    $id = $this->id;
    $regionId = $region->getId();
    $q = "SELECT ($id = ANY ("
       . "SELECT LanguageIx FROM RegionLanguages_$sid "
       . "WHERE CONCAT(StudyIx, FamilyIX, SubFamilyIx, RegionGpIx) = $regionId"
       . "))";
    $r = $this->fetchOneBy($q);
    Stopwatch::stop('Language:belongsToRegion');
    return ($r[0] == 1);
  }
  /**
    @return $isHistorical Bool
    A Language is Historical if any of it's regions is.
  */
  public function isHistorical(){
    foreach($this->getRegions() as $r)
      if($r->isHistorical())
        return true;
    return false;
  }
  /**
    @return $study Study
    Fetches the Study a language belongs in.
  */
  public function getStudy(){
    //Usual studyselection:
    $id = $this->id;
    $q  = "SELECT Name, StudyIx, FamilyIx, SubFamilyIx FROM Studies";
    $sRows = $this->fetchAllBy($q);
    //Building queries:
    $q1s = array(); $q2s = array();
    foreach($sRows as $sRow){
      $name = $sRow[0];
      $sIx  = $sRow[1];
      $fIx  = $sRow[2];
      $sFix = $sRow[3];
      array_push($q1s, "SELECT COUNT(*), '$name' FROM RegionLanguages_$name "
                     . "WHERE StudyIx = $sIx AND FamilyIx = $fIx "
                     . "AND SubFamilyIx = $sFix AND LanguageIx = $id");
      array_push($q2s, "SELECT COUNT(*), '$name' FROM RegionLanguages_$name "
                     . "WHERE StudyIx = $sIx AND FamilyIx = $fIx "
                     . "AND LanguageIx = $id");
    }
    //Searching the matching study:
    foreach(array_merge($q1s, $q2s) as $q){
      if($r = $this->fetchOneBy($q)){
        if($r[0] > 0){
          return new StudyFromKey($this->v, $r[1]);
        }
      }
    }
    /*
      Fallback on selecting Study as prefix of id:
      In this case the Prefix will be the CONCAT(StudyIx, FamilyIx, SubFamilyIx)
      from the Studies Table except for trailing zeroes which shall
      be interpreted as wildcards.
    */
    $q = "SELECT Name FROM Studies WHERE $id LIKE "
       . "CONCAT(REPLACE(CONCAT(StudyIx, FamilyIx, SubFamilyIx),'0',''),'%') "
       . "ORDER BY CONCAT(StudyIx, FamilyIx, SubFamilyIx) LIMIT 1";
    if($r = $this->fetchOneBy($q))
      return new StudyFromKey($this->v, $r[0]);
    //No Study found:
    Config::error("No Study found for LanguageIx: $id.");
  }
  /**
    @param [$html=true] Bool - decides if getFlag produces a html img tag or the raw flag url.
    @return $flag String - maybe an empty one
    Builds an img tag for the flag image of a language.
  */
  public function getFlag($html = true){
    $sid = $this->getValueManager()->getStudy()->getId();
    $id = $this->id;
    $q = "SELECT Flag FROM Languages_$sid WHERE LanguageIx = $id AND Flag != '' AND FLAG IS NOT NULL";
    if($r = $this->fetchOneBy($q)){
      $flag = $r[0];
      $q = "SELECT Tooltip FROM FlagTooltip WHERE Flag = '$flag'";
      $tooltip = '';
      if($r = Config::getConnection()->query($q)->fetch_row())
        $tooltip = $r[0];
      if($html)
        return "<img src='img/flags/$flag.png' title='$tooltip' />";
      return "img/flags/$flag.png";
    }
    return '';
  }
  /**
    @return [$location] Array
    Fetches latitude and longtitude of a Language
    and returns it as an array with two fields.
    array[0] = latitude, array[1] = longtitude
    Returns null if no Location is associated with this language.
  */
  public function getLocation(){
    if($r = $this->fetchFields('Latitude, Longtitude'))
      return $r;
    return null;
  }
  /**
    @param $t Translator
    @return $mapsLink String
    Returns a link to googlemaps, where the languages location is marked.
    Link will be target='_blank'
    If no location is attached, the empty String is returned.
  */
  public function getMapsLink($t){
    $ll = $this->getLocation();
    if(!$ll) return array();
    return array(array(
      'ttip' => $t->st('tooltip_languages_link_mapview')
    , 'href' => 'http://maps.google.com/maps?z=12&q='.implode(',', $ll)
    , 'img'  => 'img/langmap.png'
    ));
  }
  /**
    @param $t Translator
    @return $wikipediaLink String
    Returns a wikipedia link building upon the IsoData table.
    Fail will produce an empty String.
  */
  public function getWikipediaLink($t){
    $tooltip = $t->st('tooltip_languages_link_wikipedia');
    $bm  = $this->getValueManager()->gtm()->getBrowserMatch();
    if($r = $this->fetchFields('ISOCode','WikipediaLinkPart')){
      $iso   = $r[0];
      $wpart = $r[1];
      $q = "SELECT Href FROM WikipediaLinks "
         . "WHERE BrowserMatch = '$bm' "
         . "AND ISOCode = '$iso' "
         . "AND WikipediaLinkPart = '$wpart'";
      if($r = $this->fetchOneBy($q))
        return array(array(
          'ttip'  => $tooltip
        , 'img'   => 'http://en.wikipedia.org/favicon.ico'
        , 'class' => 'favicon favicon-bordered'
        , 'href'  => $r[0]
        ));
    }
    return array();
  }
  /**
    @param $t Translator
    @return $links String
    Works similar to the wikipedia one.
  */
  public function getVariousLinks($t){
    $sid = $this->getValueManager()->getStudy()->getId();
    $id = $this->id;
    $q = "SELECT ISOCode FROM Languages_$sid "
       . "WHERE LanguageIx = $id AND ISOCode != ''";
    if($r = $this->fetchOneBy($q)){
      $iso = $r[0];
      return array(
        array(
          'href'  => "http://www.ethnologue.com/show_language.asp?code=$iso"
        , 'img'   => 'http://www.ethnologue.com/favicon.ico'
        , 'ttip'  => $t->st('tooltip_languages_link_ethnologue')
        )
      , array(
          'href'  => "http://www.glottolog.org/resource/languoid/iso/$iso"
        , 'img'   => 'img/extern/glottolog.png'
        , 'ttip'  => $t->st('tooltip_languages_link_glottolog')
        )
      , array(
          'href'  => "http://multitree.org/codes/$iso.html"
        , 'img'   => 'http://multitree.org/images/favicon.ico'
        , 'ttip'  => $t->st('tooltip_languages_link_multitree')
        )
      , array(
          'href'  => "http://www.llmap.org/maps/by-code/$iso.html"
        , 'style' => 'width: 36px;'
        , 'img'   => 'img/extern/llmap.png'
        , 'ttip'  => $t->st('tooltip_languages_link_llmap')
        )
      );
    }
    return array();
  }
  /**
    @param $t Translator
    @return $links String
    Combines the outputs of the functions
    get{MapsLink,WikipediaLink,VariousLinks}
    and adds the play image afterwards.
  */
  public function getLinks($t){
    return array(
      'links' => array_merge(
        $this->getVariousLinks($t)
      , $this->getWikipediaLink($t)
      , $this->getMapsLink($t)
      )
    );
  }
  /**
    @param $t Translator
    Displays the detailed information of a language.
  */
  public function getDescription($t){
    Stopwatch::start('Language:getDescription');
    $rows = array();
    //LanguageStatusType information:
    //Lst will become null|array(Status,StatusTooltip,Description,LanguageSatusType)
    $lst = $this->getLanguageStatusType();
    if($lst){
      if($t->getTarget() == 1){
        $q = "SELECT Status, StatusTooltip, Description "
           . "FROM LanguageStatusTypes WHERE LanguageStatusType = $lst";
        if($r = $this->fetchOneBy($q)){
          array_push($r, $lst);
          $lst = $r;
        }
      }else if($trans = $t->getLanguageStatusTypeTranslation($this)){
        array_push($trans, $lst);
        $lst = $trans;
      }else{
        $lst = null;
      }
    }
    //Helper function to test array fields:
    $test = function($arr, $key){
      if(array_key_exists($key, $arr)){
        if($arr[$key] != '')
          return true;
      }
      return false;
    };
    //Language information to build the description:
    if($r = $this->fetchAssoc(
          'Tooltip'
        , 'SpecificLanguageVarietyName'
        , 'WebsiteSubgroupName'
        , 'WebsiteSubgroupWikipediaString'
        , 'HistoricalPeriod'
        , 'HistoricalPeriodWikipediaString'
        , 'EthnicGroup'
        , 'StateRegion'
        , 'NearestCity'
        , 'PreciseLocality'
        , 'PreciseLocalityNationalSpelling'
        , 'ExternalWeblink')
      ){
      //Description lines:
      if($r['Tooltip']){
        array_push($rows, array(
          'desc' => $r['Tooltip']
        ));
      }
      //Historical Period
      if($test($r, 'HistoricalPeriod')){
        if($lst && $lst[3] == 1){
          array_push($rows, array(
            'desc' => $lst[2].' '.$r['HistoricalPeriod']
          , 'link' => 'http://en.wikipedia.org/wiki/'.$r['HistoricalPeriodWikipediaString']
          , 'img'  => 'http://en.wikipedia.org/favicon.ico'
          ));
          $lst = null;
        }else{
          array_push($rows, array(
            'desc' => $t->st('language_description_historical').': '.$r['HistoricalPeriod']
          , 'link' => 'http://en.wikipedia.org/wiki/'.$r['HistoricalPeriodWikipediaString']
          , 'img'  => 'http://en.wikipedia.org/favicon.ico'
          ));
        }
      }
      //Add in Ethnic group here .)
      if($lst && $lst[3] == 6){
        if($test($r, 'EthnicGroup')){
          array_push($rows, array(
            'desc' => $lst[2].' '.$r['EthnicGroup']
          ));
        }
        $lst = null;
      }
      //Region
      if($test($r, 'NearestCity') || $test($r, 'StateRegion')){
        $cty = ($test($r, 'NearestCity') && $test($r, 'StateRegion'))
             ?  $r['NearestCity'].' ('.$r['StateRegion'].')'
             :  $r['StateRegion'];
        $str = $lst ? $lst[2] : $t->st('language_description_region');
        $str.= ': '.$cty;
        array_push($rows, array('desc' => $str));
        if(isset($lst)) $lst = null;
      }
      //Still have LST? - Fallback to LongName!
      if($lst){
        array_push($rows, array(
          'desc' => $lst[2].' '.$this->getLongName(false)
        ));
        $lst = null;
      }
      //Locality
      if($test($r, 'PreciseLocality')){
        $pLocality    = $r['PreciseLocality'];
        $pNatSpelling = $test($r, 'PreciseLocalityNationalSpelling')
                      ? ' (='.$r['PreciseLocalityNationalSpelling'].')' : '';
        array_push($rows, array(
          'desc' => $t->st('language_description_preciselocality').": $pLocality$pNatSpelling"
        ));
      }
      //External Weblink
      if($test($r, 'ExternalWeblink')){
        array_push($rows, array(
          'desc' => $t->st('language_description_externalweblink').': '
        , 'link' => $r['ExternalWeblink']
        , 'text' => $r['ExternalWeblink']
        ));
      }
      //WebsiteSubgroup
      if($test($r, 'WebsiteSubgroupName')){
        array_push($rows, array(
          'desc'      => $t->st('language_description_subgroup').': '
        , 'link'      => $test($r, 'WebsiteSubgroupWikipediaString')
                       ? 'http://en.wikipedia.org/wiki/'.$r['WebsiteSubgroupWikipediaString']
                       : null
        , 'img'       => 'http://en.wikipedia.org/favicon.ico'
        , 'afterLink' => $r['WebsiteSubgroupName']
        ));
      }
    }
    Stopwatch::stop('Language:getDescription');
    return array('rows' => $rows);
  }
  /**
    @return $familyIx FamilyIx from v4.Languages table
    Introduced because it determines the backgroundcolor
    of transcriptions on mapview.
    Defaults to 1
  */
  public function getFamilyIx(){
    if($r = $this->fetchFields('FamilyIx'))
      return $r[0];
    return 1;
  }
  /**
    @param $v ValueManager
    @param $next Bool false means prev
    @return $language Language
    This function works similar to database/Word:getNeighbour.
    It returns the next/prev Language from the current study as sorted by Id.
    Languages wrap around, meaning the last Language is the prev to the first.
    However, this function is easier than the Word implementation,
    because Languages are always sorted by their Id, and never alphabetically.
  */
  private function getNeighbour($v, $next){
    Stopwatch::start('Language:getNeighbour');
    // Setting $order and $comp depending on $next:
    $order = $next ? 'ASC' : 'DESC';
    $comp  = $next ? '>'   : '<';
    //Setting Ids:
    $sId = $v->getStudy()->getId();
    $lId = $this->id;
    //The default Query:
    $q = "SELECT LanguageIx FROM RegionLanguages_$sId WHERE "
       . "(RegionGpIx, RegionMemberLgIx) $comp (SELECT RegionGpIx, RegionMemberLgIx FROM RegionLanguages_$sId WHERE LanguageIx = $lId) "
       . "ORDER BY RegionGpIx $order, RegionMemberLgIx $order LIMIT 1";
    if($r = Config::getConnection()->query($q)->fetch_row()){
      Stopwatch::stop('Language:getNeighbour');
      return new LanguageFromId($v, $r[0]);
    }
    //The wrap around case:
    $q = "SELECT LanguageIx FROM RegionLanguages_$sId ORDER BY RegionGpIx $order, RegionMemberLgIx $order LIMIT 1";
    $r = Config::getConnection()->query($q)->fetch_row();
    Stopwatch::stop('Language:getNeighbour');
    return new LanguageFromId($v, $r[0]);
  }
  /**
    @param $v ValueManager
    @return $next Language
    getNext acts as a proxy to getNeighbour.
  */
  public function getNext($v){
    return $this->getNeighbour($v, true);
  }
  /**
    @param $v ValueManager
    @return $prev Language
    getPrev acts as a proxy to getNeighbour.
  */
  public function getPrev($v){
    return $this->getNeighbour($v, false);
  }
  /**
    @return $phLang Language
    Returns the AssociatedPhoneticsLgForThisSpellingLg for a Language,
    if IsSpellingRfcLang = 1, or $this.
  */
  public function getPhoneticLanguage(){
    Stopwatch::start('Language:getPhoneticLanguage');
    $r = $this->fetchFields('IsSpellingRfcLang','AssociatedPhoneticsLgForThisSpellingLg');
    if($r[0] == "1" && $r[1]){
      $id  = $this->id;
      $lid = $r[1];
      Stopwatch::stop('Language:getPhoneticLanguage');
      return new LanguageFromId($this->getValueManager(), $r[1]);
    }
    Stopwatch::stop('Language:getPhoneticLanguage');
    return $this;
  }
  /**
    @return [Contributor] contributors
  */
  public function getContributors(){
    return Contributor::forLanguage($this);
  }
  /***/
  public function hasTranscriptions(){
    $r = $this->fetchFields('IsOrthographyHasNoTranscriptions');
    if($r[0] == '1') return false;
    return true;
  }
  /**
    @param Language[] languages
    @return {regions: RegionId -> Region, buckets: RegionId -> Language[]}
    Note that the languages in the buckets are enumerated,
    which is helpful to answer the question at which place a language is
    in relation to the ones in all other buckets.
  */
  public static function mkRegionBuckets($languages){
    Stopwatch::start('Language:mkRegionBuckets');
    $regions = array(); // RegionId -> Region
    $buckets = array(); // RegionId -> Language[]
    //Sorting into buckets:
    foreach($languages as $l){
      $r = $l->getRegion();
      if($r === null){
        Config::error('database/Language.php:mkregionBuckets '
                     .'cannot find Region for LanguageIx: '
                     .$l->getId());
        continue;
      }
      $rId  = $r->getId();
      if(!array_key_exists($rId, $regions)){
        $regions[$rId] = $r;
        $buckets[$rId] = array($l);
      }else{
        array_push($buckets[$rId], $l);
      }
    }
    //Enumerating languages in buckets:
    $i = 0;
    foreach($buckets as $rId => $bucket){
      $newBucket = array();
      foreach($bucket as $l){
        $newBucket[$i] = $l;
        $i++;
      }
      $buckets[$rId] = $newBucket;
    }
    //Done:
    Stopwatch::stop('Language:mkRegionBuckets');
    return array('regions' => $regions, 'buckets' => $buckets);
  }
}
/** Extends Language so that it can be created from an id. */
class LanguageFromId extends Language{
  /**
    @param $v ValueManager
    @param $id LanguageIx
  */
  public function __construct($v, $id){
    $this->setup($v);
    $this->id = $id;
    $this->findKey();
  }
}
/** Extends Language so that it can be created from a key. */
class LanguageFromKey extends Language{
  /**
    @param $v ValueManager
    @param $key String - shortname of the language.
  */
  public function __construct($v, $key){
    $this->setup($v);
    $this->key = $key;
    $this->id  = null;
    if(preg_match('/^([^<]+)(<(.*)>)?$/', $key, $matches)){
      $sId  = $v->gsm()->getStudy()->getId();
      $name = $matches[1];
      $qs   = array();
      if(count($matches) === 4){
        //Got a LanguageStatusType
        $type = $matches[3];
        array_push($qs,
          "SELECT L.LanguageIx "
        . "FROM Languages_$sId AS L "
        . "JOIN LanguageStatusTypes AS T USING (LanguageStatusType) "
        . "WHERE T.Status = '$type' "
        . "AND L.ShortName = '$name'");
      }else{
        //No LanguageStatusType given.
    $qs = array(
        "SELECT LanguageIx FROM RegionLanguages_$sId "
      . "WHERE RegionGpMemberLgNameLongInThisSubFamilyWebsite LIKE '$name'"
      , "SELECT LanguageIx FROM RegionLanguages_$sId "
      . "WHERE RegionGpMemberLgNameShortInThisSubFamilyWebsite LIKE '$name'"
      , "SELECT LanguageIx FROM Languages_$sId WHERE ShortName LIKE '$name'"
    );
      }
      foreach($qs as $q){
        if($r = $this->fetchOneBy($q)){
          $this->id = $r[0];
          break;
        }
      }
    }else{
      Config::error("Could not parse LanguageKey: $key");
    }
    if($this->id == null)
      Config::error("No Id found for LanguageKey: $key");
  }
}
/** Extends Language so that it can be created from a Study. */
class LanguageFromStudy extends Language{
  /**
    @param $v ValueManager
    @param $study Study
    Fetches the first Language in a study sorting by LanguageIx asc.
  */
  public function __construct($v, $study){
    $this->setup($v);
    $sid = $study->getId();
    //Fetching LanguageIx
    $q = "SELECT LanguageIx FROM Languages_$sid ORDER BY LanguageIx ASC LIMIT 1";
    if($r = Config::getConnection()->query($q)->fetch_row()){
      $this->id = $r[0];
    }else Config::error("Could not find language for studyId: $sid.");
    $this->findKey();
  }
}
?>