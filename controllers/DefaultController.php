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

class DefaultController extends BaseEventTypeController
{
	// TODO: check this is in line with Jamie's change circa 3rd April 2013
	protected function beforeAction($action)
	{
		if (!Yii::app()->getRequest()->getIsAjaxRequest() && !(in_array($action->id,$this->printActions())) ) {
			Yii::app()->getClientScript()->registerCssFile(Yii::app()->createUrl('css/spliteventtype.css'));
			Yii::app()->getClientScript()->registerScriptFile(Yii::app()->createUrl('js/spliteventtype.js'));
		}

		$res = parent::beforeAction($action);

		return $res;
	}

	public function printActions()
	{
		return array('print', 'processApplication');
	}

	public function addEditJSVars()
	{
		$this->jsVars['decisiontree_url'] = Yii::app()->createUrl('OphCoTherapyapplication/default/getDecisionTree/');
		$this->jsVars['nhs_date_format'] = Helper::NHS_DATE_FORMAT_JS;
	}

	public function actionCreate()
	{
		$this->addEditJSVars();
		parent::actionCreate();
	}

	public function actionUpdate($id)
	{
		$this->addEditJSVars();
		parent::actionUpdate($id);
	}

	public function actionView($id)
	{
		parent::actionView($id);
	}

	public function actionPrint($id)
	{
		parent::actionPrint($id);
	}

	private $event_model_cache = array();

	/**
	 * preview of the application - will generate both left and right forms into one PDF
	 *
	 * @throws CHttpException
	 */
	public function actionPreviewApplication()
	{
		if (isset($_REQUEST['event_id'])) {
			if ($ec = Element_OphCoTherapyapplication_ExceptionalCircumstances::model()->find(array(
					'condition' => 'event_id = :evid',
					'params' => array(':evid' => (int) $_REQUEST['event_id']))) ) {

				$pdfbodies = array();
				$service = new OphCoTherapyapplication_Processor();
				if ($ec->hasRight()) {
					$pdfbodies[] = $service->generateEventPDFTemplateForSide($ec->event_id, 'right');
				}
				if ($ec->hasLeft()) {
					$pdfbodies[] = $service->generateEventPDFTemplateForSide($ec->event_id, 'left');
				}

				// have to use a wrapper to combine multiple forms
				$pdfwrapper = new OETCPDF();
				$pdfwrapper->SetAuthor($ec->usermodified->fullName);
				$pdfwrapper->SetTitle('Therapy application preview');
				$pdfwrapper->SetSubject('Therapy application');

				foreach($pdfbodies as $body) {
					$body->render($pdfwrapper);
				}
				$pdfwrapper->Output("Therapy Application.pdf", "I");
			} else {
				throw new CHttpException('404', 'Exceptional Circumstances not found for event');
			}
		} else {
			throw new CHttpException('400', 'Invalid request');
		}
	}

	/**
	 * actually generates and submits the therapy application
	 *
	 * @throws CHttpException
	 */
	public function actionProcessApplication()
	{
		if (isset($_REQUEST['event_id'])) {
			$service = new OphCoTherapyapplication_Processor();
			$event_id = (int) $_REQUEST['event_id'];
			if ($service->canProcessEvent($event_id)) {
				if ($service->processEvent($event_id)) {
					Yii::app()->user->setFlash('success', "Application processed.");
				} else {
					Yii::app()->user->setFlash('error', "Unable to process the application at this time.");
				}
			}
			$this->redirect(array($this->successUri.$event_id));
		} else {
			throw new CHttpException('400', 'Invalid request');
		}
	}

	public function actionDownloadFileCollection($id)
	{
		if ($collection = OphCoTherapyapplication_FileCollection::model()->findByPk((int) $id)) {
			$pf = $collection->getZipFile();
			if ($pf) {
				$this->redirect($pf->getDownloadURL());
			}
		}
		throw new CHttpException('400', 'File Collection does not exist');
	}

	/**
	 * extends the base function to set various defaults that depend on other events etc
	 *
	 * (non-PHPdoc)
	 * @see BaseEventTypeController::getDefaultElements($action, $event_type_id, $event)
	 */
	public function getDefaultElements($action, $event_type_id=false, $event=false)
	{
		$all_elements = parent::getDefaultElements($action, $event_type_id, $event);

		if (in_array($action, array('create', 'edit'))) {
			// clear out the email element as we don't want to display or edit it
			$elements = array();
			foreach ($all_elements as $element) {
				if (get_class($element) != 'Element_OphCoTherapyapplication_Email') {
					$elements[] = $element;
				}
			}
		} else {
			$elements = $all_elements;
		}

		if ($action == 'create' && empty($_POST)) {
			// set any calculated defaults on the elements
			foreach ($elements as $element) {
				if (get_class($element) == 'Element_OphCoTherapyapplication_Therapydiagnosis') {
					// get the list of valid diagnosis codes
					$valid_disorders = OphCoTherapyapplication_TherapyDisorder::model()->findAll();
					$vd_ids = array();
					foreach ($valid_disorders as $vd) {
						$vd_ids[] = $vd->disorder_id;
					}

					$episode = $this->episode;

					if ($episode) {

						// foreach eye
						$exam_api = Yii::app()->moduleAPI->get('OphCiExamination');
						foreach (array(SplitEventTypeElement::LEFT, SplitEventTypeElement::RIGHT) as $eye_id) {
							$prefix = $eye_id == SplitEventTypeElement::LEFT ? 'left' : 'right';
							// get specific disorder from injection management
							if ($exam_api && $exam_imc = $exam_api->getInjectionManagementComplexInEpisodeForSide($this->patient, $episode, $prefix)) {
								$element->{$prefix . '_diagnosis1_id'} = $exam_imc->{$prefix . '_diagnosis1_id'};
								$element->{$prefix . '_diagnosis2_id'} = $exam_imc->{$prefix . '_diagnosis2_id'};
							}
							// check if the episode diagnosis applies
							elseif ( ($episode->eye_id == $eye_id || $episode->eye_id == SplitEventTypeElement::BOTH)
								&& in_array($episode->disorder_id, $vd_ids) ) {
								$element->{$prefix . '_diagnosis1_id'} = $episode->disorder_id;
							}
							// otherwise get ordered list of diagnoses for the eye in this episode, and check
							else {
								if ($exam_api) {
									$disorders = $exam_api->getOrderedDisorders($this->patient, $episode);
									foreach ($disorders as $disorder) {
										if ( ($disorder['eye_id'] == $eye_id || $disorder['eye_id'] == 3) && in_array($disorder['disorder_id'], $vd_ids)) {
											$element->{$prefix . '_diagnosis1_id'} = $disorder['disorder_id'];
											break;
										}
									}
								}
							}
						}
					}

				} // end Therapydiagnosis setup
				elseif (get_class($element) == 'Element_OphCoTherapyapplication_MrServiceInformation') {
					$element->consultant_id = Yii::app()->session['selected_firm_id'];
					$element->site_id = Yii::app()->session['selected_site_id'];
				}

				// set the correct eye_id on the element for rendering
				if(isset($element->left_diagnosis1_id) && isset($element->right_diagnosis1_id)){
					$element->eye_id = SplitEventTypeElement::BOTH;
				}
				else if(isset($element->left_diagnosis1_id)){
					$element->eye_id = SplitEventTypeElement::LEFT;
				}
				else if(isset($element->right_diagnosis1_id)){
					$element->eye_id = SplitEventTypeElement::RIGHT;
				}

			}
		}
		return $elements;

	}

	/**
	 * ajax action to retrieve a specific decision tree (which can then be populated with appropriate default values
	 *
	 * @throws CHttpException
	 */
	public function actionGetDecisionTree()
	{
		if (!$this->patient = Patient::model()->findByPk((int) @$_GET['patient_id'])) {
			throw new CHttpException(403, 'Invalid patient_id.');
		}
		if (!$treatment = OphCoTherapyapplication_Treatment::model()->findByPk((int) @$_GET['treatment_id']) ) {
			throw new CHttpException(403, 'Invalid treatment_id.');
		}

		$element = new Element_OphCoTherapyapplication_PatientSuitability();

		$side = @$_GET['side'];
		if (!in_array($side, array('left', 'right'))) {
			throw Exception('Invalid side argument');
		}

		$element->{$side . '_treatment'} = $treatment;

		$form = Yii::app()->getWidgetFactory()->createWidget($this,'BaseEventTypeCActiveForm',array(
				'id' => 'clinical-create',
				'enableAjaxValidation' => false,
				'htmlOptions' => array('class' => 'sliding'),
		));

		$this->renderPartial(
				'form_OphCoTherapyapplication_DecisionTree',
				array('element' => $element, 'form' => $form, 'side' => $side),
				false, false
				);
	}

	/**
	 * works out the node response value for the given node id on the element. Basically allows us to check for
	 * submitted values, values stored against the element from being saved, or working out a default value if applicable
	 *
	 * @param Element_OphCoTherapyapplication_PatientSuitability $element
	 * @param string $side
	 * @param integer $node_id
	 */
	public function getNodeResponseValue($element, $side, $node_id)
	{
		if (isset($_POST['Element_OphCoTherapyapplication_PatientSuitability'][$side . '_DecisionTreeResponse']) ) {
			// responses have been posted, so should operate off the value for this node.
			return @$_POST['Element_OphCoTherapyapplication_PatientSuitability'][$side . '_DecisionTreeResponse'][$node_id];
		}
		foreach ($element->{$side . '_responses'} as $response) {
			if ($response->node_id == $node_id) {
				return $response->value;
			}
		}
		$node = OphCoTherapyapplication_DecisionTreeNode::model()->findByPk($node_id);

		return $node->getDefaultValue($side, $this->patient, $this->episode);
	}

	/**
	 * process the POST data for past interventions for the given side
	 *
	 * @param Element_OphCoTherapyapplication_ExceptionCircumstances $element
	 * @param string $side - left or right
	 */
	private function _POSTPastinterventions($element, $side)
	{
		foreach (array('_previnterventions' => false, '_relevantinterventions' => true) as $past_type => $is_relevant) {
			if (isset($_POST['Element_OphCoTherapyapplication_ExceptionalCircumstances'][$side . $past_type]) ) {
				$pastinterventions = array();
				foreach ($_POST['Element_OphCoTherapyapplication_ExceptionalCircumstances'][$side . $past_type] as $idx => $attributes) {
					// we have 1 or more entries that are just indexed by a counter. They may or may not already be in the db
					// but at this juncture we don't care, we just want to create a previous intervention for this side and attach to
					// the element
					$past = new OphCoTherapyapplication_ExceptionalCircumstances_PastIntervention();
					$past->attributes = Helper::convertNHS2MySQL($attributes);
					if ($side == 'left') {
						$past->exceptional_side_id = SplitEventTypeElement::LEFT;
					} else {
						$past->exceptional_side_id = SplitEventTypeElement::RIGHT;
					}
					$past->is_relevant = $is_relevant;

					$pastinterventions[] = $past;
				}
				$element->{$side . $past_type} = $pastinterventions;
			}
		}
	}

	/**
	 * process the POST data for deviation reasons for the given side
	 *
	 * @param Element_OphCoTherapyapplication_ExceptionalCircumstances $element
	 * @param string $side - left or right
	 */
	private function _POSTDeviationReasons($element, $side)
	{
		if (isset($_POST['Element_OphCoTherapyapplication_ExceptionalCircumstances'][$side . '_deviationreasons']) ) {
			$dr_lst = array();
			foreach ($_POST['Element_OphCoTherapyapplication_ExceptionalCircumstances'][$side . '_deviationreasons'] as $id) {
				if ($dr = OphCoTherapyapplication_ExceptionalCircumstances_DeviationReason::model()->findByPk((int) $id)) {
					$dr_lst[] = $dr;
				}
			}
			$element->{$side . '_deviationreasons'} = $dr_lst;
		}
	}

	/**
	 * (non-PHPdoc)
	 * @see BaseEventTypeController::setPOSTManyToMany()
	 */
	protected function setPOSTManyToMany($element)
	{
		if (get_class($element) == "Element_OphCoTherapyapplication_ExceptionalCircumstances") {
			$this->_POSTPastinterventions($element, 'left');
			$this->_POSTPastinterventions($element, 'right');
			$this->_POSTDeviationReasons($element, 'left');
			$this->_POSTDeviationReasons($element, 'right');
		}
	}

	/*
	 * ensures Many Many fields processed for elements
	*/
	public function createElements($elements, $data, $firm, $patientId, $userId, $eventTypeId)
	{
		if ($id = parent::createElements($elements, $data, $firm, $patientId, $userId, $eventTypeId)) {
			// create has been successful, store many to many values
			$this->storePOSTManyToMany($elements);
		}
		return $id;
	}

	/**
	 * similar to setPOSTManyToMany, but will actually call methods on the elements that will create database entries
	 * should be called on create and update.
	 *
	 * @param Element[] - array of elements being created
	 */
	protected function storePOSTManyToMany($elements)
	{
		foreach ($elements as $el) {
			if (get_class($el) == 'Element_OphCoTherapyapplication_PatientSuitability') {
				// note we don't do this in POST Validation as we don't need to validate the values of the decision tree selection
				// this is really just for record keeping - we are mainly interested in whether or not it's got compliance value
				$el->updateDecisionTreeResponses(Element_OphCoTherapyapplication_PatientSuitability::LEFT,
						isset($_POST['Element_OphCoTherapyapplication_PatientSuitability']['left_DecisionTreeResponse']) ?
						$_POST['Element_OphCoTherapyapplication_PatientSuitability']['left_DecisionTreeResponse'] :
						array());
				$el->updateDecisionTreeResponses(Element_OphCoTherapyapplication_PatientSuitability::RIGHT,
						isset($_POST['Element_OphCoTherapyapplication_PatientSuitability']['right_DecisionTreeResponse']) ?
						$_POST['Element_OphCoTherapyapplication_PatientSuitability']['right_DecisionTreeResponse'] :
						array());

			} elseif (get_class($el) == 'Element_OphCoTherapyapplication_ExceptionalCircumstances') {
				foreach (array('left' => Eye::LEFT, 'right' => Eye::RIGHT) as $side_str => $side_id) {
					$el->updateDeviationReasons($side_id,
							isset($_POST['Element_OphCoTherapyapplication_ExceptionalCircumstances'][$side_str . '_deviationreasons']) ?
							Helper::convertNHS2MySQL($_POST['Element_OphCoTherapyapplication_ExceptionalCircumstances'][$side_str . '_deviationreasons']) :
							array());
					$el->updatePreviousInterventions($side_id,
							isset($_POST['Element_OphCoTherapyapplication_ExceptionalCircumstances'][$side_str . '_previnterventions']) ?
							Helper::convertNHS2MySQL($_POST['Element_OphCoTherapyapplication_ExceptionalCircumstances'][$side_str . '_previnterventions']) :
							array());
					$el->updateRelevantInterventions($side_id,
						isset($_POST['Element_OphCoTherapyapplication_ExceptionalCircumstances'][$side_str . '_relevantinterventions']) ?
							Helper::convertNHS2MySQL($_POST['Element_OphCoTherapyapplication_ExceptionalCircumstances'][$side_str . '_relevantinterventions']) :
							array());
					$el->updateFileCollections($side_id,
							isset($_POST['Element_OphCoTherapyapplication_ExceptionalCircumstances'][$side_str . '_filecollections']) ?
							$_POST['Element_OphCoTherapyapplication_ExceptionalCircumstances'][$side_str . '_filecollections'] :
							array());
				}
			}
		}
	}

	/*
	 * ensures Many Many fields processed for elements
	*/
	public function updateElements($elements, $data, $event)
	{
		if ($response = parent::updateElements($elements, $data, $event)) {
			// update has been successful, now need to deal with many to many changes
			$this->storePOSTManyToMany($elements);
		}
		return $response;
	}

	public function hasDiagnosisForSide($event_id, $side) {
		if (!empty($_POST)) {
			return @$_POST['Element_OphCoTherapyapplication_Therapydiagnosis'][$side.'_diagnosis1_id'];
		} else {
			if ($event_id) {
				if ($element = Element_OphCoTherapyapplication_Therapydiagnosis::model()->find('event_id=?',array($event_id))) {
					if ($side == 'left') {
						return $element->hasLeft();
					}
					return $element->hasRight();
				}
			}
		}
		return false;
	}
}
