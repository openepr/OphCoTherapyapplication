<?php 
/**
 * OpenEyes
 *
 * (C) Moorfields Eye Hospital NHS Foundation Trust, 2008-2011
 * (C) OpenEyes Foundation, 2011-2013
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEyes
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2008-2011, Moorfields Eye Hospital NHS Foundation Trust
 * @copyright Copyright (c) 2011-2013, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/gpl-3.0.html The GNU General Public License V3.0
 */

return array(
	'params' => array(
		// fixed details for admin functionality
		'admin_menu' => array(
			'Treatments' => '/OphCoTherapyapplication/admin/viewTreatments',
			'Decision Trees' => '/OphCoTherapyapplication/admin/viewDecisionTrees',
		),
		// The email address that sends therapy applications (key/value pair of address to name)
		// 'OphCoTherapyapplication_sender_email' => array('email@test.com' => 'Test'),
		// The email address(es) that receives compliant therapy applications (key/value pair(s) of address to name)
		// 'OphCoTherapyapplication_compliant_recipient_email' => array('email@test.com' => 'Email Test'),
		// The email address(es) that receives NON compliant therapy applications (key/value pair(s) of address to name)
		// 'OphCoTherapyapplication_noncompliant_recipient_email' => array('email2@test.com' => 'Email Test 2'),
		// The email address displayed in the standard non-compliant form
		// 'OphCoTherapyapplication_applicant_email' => 'armd@nhs.net',
		// postal details of the chief pharmacist (string of name and address)
		// 'OphCoTherapyapplication_chief_pharmacist' => '',
		// contact details of the chief pharmacist (string)
		// 'OphCoTherapyapplication_chief_pharmacist_contact' => '',
	),
);