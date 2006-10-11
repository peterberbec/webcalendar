<?php
/*
 * $Id$
 *
 * Page Description:
 * This page will display the month "view" with all users's events
 * on the same calendar.  (The other month "view" displays each user
 * calendar in a separate column, side-by-side.)  This view gives you
 * the same effect as enabling layers, but with layers you can only
 * have one configuration of users.
 *
 * Input Parameters:
 * id (*) - specify view id in webcal_view table
 * date - specify the starting date of the view.
 *   If not specified, current date will be used.
 * friendly - if set to 1, then page does not include links or
 *   trailer navigation.
 * (*) required field
 *
 * Security:
 * Must have "allow view others" enabled ($ALLOW_VIEW_OTHER) in
 *   System Settings unless the user is an admin user ($is_admin).
 * If the view is not global, the user must be owner of the view.
 * If the view is global, then and user_sees_only_his_groups is
 * enabled, then we remove users not in this user's groups
 * (except for nonuser calendars... which we allow regardless of group).
 */
include_once 'includes/init.php';
include_once 'includes/views.php';

$error = '';
$DAYS_PER_TABLE = 7;

view_init ( $id );

$INC = array('js/popups.php');
print_header($INC);

set_today($date);

$next = mktime ( 0, 0, 0, $thismonth, $thisday + 7, $thisyear );
$nextyear = date ( 'Y', $next );
$nextmonth = date ( 'm', $next );
$nextday = date ( 'd', $next );
$nextdate = sprintf ( "%04d%02d%02d", $nextyear, $nextmonth, $nextday );

$prev = mktime ( 0, 0, 0, $thismonth, $thisday - 7, $thisyear );
$prevyear = date ( 'Y', $prev );
$prevmonth = date ( 'm', $prev );
$prevday = date ( 'd', $prev );
$prevdate = sprintf ( "%04d%02d%02d", $prevyear, $prevmonth, $prevday );

$wkstart = get_weekday_before ( $thisyear, $thismonth, $thisday +1 );

$wkend = $wkstart + ( ONE_DAY * ( $DISPLAY_WEEKENDS == 'N'? 5 : 7 ) );
$thisdate = date ( 'Ymd', $wkstart );


for ( $i = 0; $i < 7; $i++ ) {
  $days[$i] = $wkstart + ONE_DAY * $i;
  $weekdays[$i] = weekday_short_name ( ( $i + $WEEK_START ) % 7 );
  $header[$i] = $weekdays[$i] . '<br />' .
     month_short_name ( date ( 'm', $days[$i] ) - 1 ) .
     ' ' . date ( 'd', $days[$i] );
}
?>

<div style="width:99%;">
<a title="<?php etranslate ( 'Previous' )?>" class="prev" 
  href="view_v.php?id=<?php echo $id?>&amp;date=<?php echo $prevdate?>">
  <img src="images/leftarrow.gif" alt="<?php etranslate ( 'Previous' )?>" /></a>

<a title="<?php etranslate ( 'Next' )?>" class="next" 
  href="view_v.php?id=<?php echo $id?>&amp;date=<?php echo $nextdate?>">
  <img src="images/rightarrow.gif" class="prevnext" alt="<?php etranslate ( 'Next' )?>" /></a>
<div class="title">
<span class="date"><?php
  echo date_to_str ( date ( 'Ymd', $wkstart ), '', false ) .
    '&nbsp;&nbsp;&nbsp; - &nbsp;&nbsp;&nbsp;' .
    date_to_str ( date ( 'Ymd', $wkend ), '', false );
?></span><br />
<span class="viewname"><?php echo htmlspecialchars ( $view_name ); ?></span>
</div></div><br />

<?php
// The table has names across the top and dates for rows.  Since we need
// to spit out an entire row before we can move to the next date, we'll
// save up all the HTML for each cell and then print it out when we're
// done..
// Additionally, we only want to put at most 6 users in one table since
// any more than that doesn't really fit in the page.

// get users in this view
$viewusers = view_get_user_list ( $id );
$viewusercnt = count ( $viewusers );
if ( $viewusercnt == 0 ) {
  // This could happen if user_sees_only_his_groups  = Y and
  // this user is not a member of any  group assigned to this view
  $error = translate ( 'No users for this view' ) ;
}

if ( ! empty ( $error ) ) {
  echo print_error ( $error );
  echo print_trailer ();
  exit;
}

$e_save = array ();
$re_save = array ();
for ( $i = 0; $i < $viewusercnt; $i++ ) {
  /* Pre-Load the repeated events for quicker access */
  $re_save[$i] = read_repeated_events ( $viewusers[$i], '', $wkstart, $wkend );
  /* Pre-load the non-repeating events for quicker access 
     subtracting ONE_WEEK to allow cross-dat events to display*/
  $e_save[$i] = read_events ( $viewusers[$i], $wkstart - ONE_WEEK, $wkend );
}

for ( $j = 0; $j < 7; $j += $DAYS_PER_TABLE ) {
  // since print_date_entries is rather stupid, we can swap the event data
  // around for users by changing what $events points to.

  $tdw = 12; // column width percent
?>

<table class="main">
<tr><th class="empty">&nbsp;</th>
<?php
  for ( $date = $wkstart, $h = 0;
    date ( 'Ymd', $date ) <= date ( 'Ymd', $wkend );
    $date += ONE_DAY, $h++ ) {
    $wday = strftime ( "%w", $date );
    if ( ( $wday == 0 || $wday == 6 ) && $DISPLAY_WEEKENDS == 'N' ) continue; 
    $weekday = weekday_short_name ( $wday );
    if ( date ( 'Ymd', $date ) == date ( 'Ymd', $today ) ) {
      echo "<th class=\"today\" style=\"width:$tdw%;\">";
    } else {
      echo "<th style=\"width:$tdw%;\">";
    }
    echo $weekday . " " .
    round ( date ( 'd', $date ) ) . "</th>\n";
  }
  echo "</tr>\n";
  for ( $i = 0; $i < $viewusercnt; $i++ ) {
    $events = $e_save[$i];
    $repeated_events = $re_save[$i];
    echo "\n<tr>\n";
    $user = $viewusers[$i];
    user_load_variables ( $user, 'temp' );
    echo "<th class=\"row\" style=\"width:$tdw%;\">$tempfullname</th>";
    for ( $date = $wkstart, $h = 0;
      date ( 'Ymd', $date ) <= date ( 'Ymd', $wkend );
      $date += ONE_DAY, $h++ ) {
      $wday = strftime ( "%w", $date );
      $entryStr = print_date_entries ( date ( 'Ymd', $date ), $user, true );
      // JCJ Correction for today class
      if ( ! empty ( $entryStr ) && $entryStr != '&nbsp;' ) {
        $class = 'class="hasevents"';
      } else if ( date ( 'Ymd', $date ) == date ( 'Ymd', $today ) ) {
        $class = 'class="today"';
      } else if ( $wday == 0 || $wday == 6 ) {
        $class = 'class="weekend"';
      } else {
        $class = '';
      }
      echo "<td $class style=\"width:$tdw%;\">";
      if ( empty ( $ADD_LINK_IN_VIEWS ) || $ADD_LINK_IN_VIEWS != 'N' ) {
        echo html_for_add_icon ( date ( 'Ymd', $date ), '', '', $user );
      }
      echo $entryStr;
      echo '</td>';
    }
    echo "</tr>\n";
  }
  echo "</table>\n";
}

$user = ''; // reset

if ( ! empty ( $eventinfo ) ) {
  echo $eventinfo;
}

echo generate_printer_friendly ( 'view_v.php');
echo print_trailer ();
?>

