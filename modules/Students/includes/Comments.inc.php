<?php

//FJ add School Configuration
$program_config = DBGet( DBQuery( "SELECT *
	FROM PROGRAM_CONFIG
	WHERE SCHOOL_ID='" . UserSchool() . "'
	AND SYEAR='" . UserSyear() . "'
	AND PROGRAM='students'" ), array(), array( 'TITLE' ) );

// set comments Marking Period
$comments_MP = UserMP();

// if Semester comment
if ( $program_config['STUDENTS_SEMESTER_COMMENTS'][1]['VALUE'] )
	$comments_MP = GetParentMP( 'SEM', UserMP() );


//$_ROSARIO['allow_edit'] = true;
if( $_REQUEST['modfunc'] === 'update'
	&& AllowEdit()
	&& isset( $_POST['values'] )
	&& trim( $_REQUEST['values']['STUDENT_MP_COMMENTS'][UserStudentID()]['COMMENT'] ) !== '' )
{
	include_once( 'ProgramFunctions/MarkDown.fnc.php' );

	// Sanitize MarkDown
	$comment = SanitizeMarkDown( $_REQUEST['values']['STUDENT_MP_COMMENTS'][UserStudentID()]['COMMENT'] );

	if ( $comment )
	{
		//FJ add time and user to comments "comment thread" like
		$comment = array(array(
			'date' => date('Y-m-d G:i:s'),
			'staff_id' => User('STAFF_ID'),
			'comment' => $comment,
		));

		$existing_RET = DBGet( DBQuery( "SELECT STUDENT_ID, COMMENT
			FROM STUDENT_MP_COMMENTS
			WHERE STUDENT_ID='" . UserStudentID() . "'
			AND SYEAR='" . UserSyear() . "'
			AND MARKING_PERIOD_ID='" . $comments_MP . "'"
		) );

		if( !$existing_RET )
			DBQuery( "INSERT INTO STUDENT_MP_COMMENTS
				(
					SYEAR,
					STUDENT_ID,
					MARKING_PERIOD_ID
				)
				values(
					'" . UserSyear() . "',
					'" . UserStudentID() . "',
					'" . $comments_MP . "'
				)"
			);

		if ( !empty( $existing_RET[1]['COMMENT'] ) )
			$comment = array_merge( $comment, (array)unserialize( $existing_RET[1]['COMMENT'] ) );

		$_REQUEST['values']['STUDENT_MP_COMMENTS'][UserStudentID()]['COMMENT'] = DBEscapeString( serialize( $comment ) );

		SaveData(
			array( 'STUDENT_MP_COMMENTS' => "STUDENT_ID='" . UserStudentID() . "'
				AND SYEAR='" . UserSyear() . "'
				AND MARKING_PERIOD_ID='" . $comments_MP . "'" ),
			'',
			array( 'COMMENT' => _( 'Comment' ) )
		);
	}
}

if( empty( $_REQUEST['modfunc'] ) )
{
	$comments_RET = DBGet( DBQuery( "SELECT COMMENT
		FROM STUDENT_MP_COMMENTS
		WHERE STUDENT_ID='" . UserStudentID() . "'
		AND SYEAR='" . UserSyear() . "'
		AND MARKING_PERIOD_ID='" . $comments_MP . "'" ) );
	
	?>

	<TABLE>
		<TR>
			<TD>
				<b><?php echo GetMP( $comments_MP, 'TITLE' ) . ' ' . _( 'Comments' ); ?></b>
				<BR />

				<?php echo TextAreaInput(
					'',
					'values[STUDENT_MP_COMMENTS][' . UserStudentID() . '][COMMENT]',
					'',
					'rows="10"' . ( AllowEdit() ? '' : ' readonly' ),
					false
				); ?>
			</TD>
		</TR>
	<?php
	//echo '<BR /><b>* '._('If more than one teacher will be adding comments for this student').':</b><BR />';
	//echo '<ul><li>'._('Type your name above the comments you enter.').'</li></ul>';
	//echo '<li>'._('Leave space for other teachers to enter their comments.').'</li></ul>';
	//FJ add time and user to comments "comment thread" like
	?>
		<TR>
			<TD id="student-comments">
	<?php
	if ( ( $comments = unserialize( $comments_RET[1]['COMMENT'] ) ) )
	{
		$comments_HTML = $staff_name = array();

		foreach( (array)$comments as $comment )
		{
			$id = $comment['staff_id'];

			if ( !isset( $staff_name[$id] ) )
			{
				if ( User('STAFF_ID') === $id )
				{
					$staff_name[$id] = User( 'NAME' );
				}
				else
				{
					$staff_name_RET = DBGet( DBQuery( "SELECT FIRST_NAME||' '||LAST_NAME AS NAME
						FROM STAFF
						WHERE SYEAR='" . UserSyear() . "'
						AND USERNAME=(
							SELECT USERNAME
							FROM STAFF
							WHERE SYEAR='" . Config( 'SYEAR' ) . "'
							AND STAFF_ID='" . $id . "'
						)" ) );

					$staff_name[$id] = $staff_name_RET[1]['NAME'];
				}
			}

			// Comment meta data: "Date hour, User name:"
			$comment_meta = '<span>' .
				ProperDate( mb_substr( $comment['date'], 0, 10 ) ) .
				mb_substr( $comment['date'], 10 ) . ', ' .
				$staff_name[$id] .
				':</span>';

			// convert MarkDown to HTML
			$comment_MD = '<div class="markdown-to-html">' . $comment['comment'] . '</div>';

			$comments_HTML[] = $comment_meta . $comment_MD;
		}

		echo implode( "\n", $comments_HTML );
	}
	?>
			</TD>
		</TR>
	</TABLE>
	<?php

	include( 'modules/Students/includes/Other_Info.inc.php' );
}
