<?php

class m130813_141710_release_tweaks extends CDbMigration
{
	public function up()
	{
		$this->addColumn('ophcotherapya_filecoll', 'summary', 'text');
		$this->addColumn('et_ophcotherapya_exceptional', 'left_patient_expectations', 'text');
		$this->addColumn('et_ophcotherapya_exceptional', 'right_patient_expectations', 'text');
		// expand to match possible values from VA values (practically unnecessary, but good for consistency)
		$this->alterColumn('ophcotherapya_patientsuit_decisiontreenoderesponse','value','varchar(255)');
		$this->addColumn('ophcotherapya_exceptional_previntervention', 'start_va', 'varchar(255)');
		$this->addColumn('ophcotherapya_exceptional_previntervention', 'end_va', 'varchar(255)');
		$this->renameColumn('ophcotherapya_exceptional_previntervention','treatment_date', 'start_date');
		$this->addColumn('ophcotherapya_exceptional_previntervention', 'end_date', 'datetime NOT NULL');
		$this->addColumn('ophcotherapya_exceptional_previntervention', 'is_relevant', 'boolean NOT NULL DEFAULT false');
		$this->renameTable('ophcotherapya_exceptional_previntervention', 'ophcotherapya_exceptional_pastintervention');
		$this->renameTable('ophcotherapya_exceptional_previntervention_stopreason', 'ophcotherapya_exceptional_pastintervention_stopreason');
	}

	public function down()
	{
		$this->renameTable('ophcotherapya_exceptional_pastintervention_stopreason', 'ophcotherapya_exceptional_previntervention_stopreason');
		$this->renameTable('ophcotherapya_exceptional_pastintervention', 'ophcotherapya_exceptional_previntervention');
		$this->dropColumn('ophcotherapya_exceptional_previntervention', 'is_relevent');
		$this->dropColumn('ophcotherapya_exceptional_previntervention', 'end_date');
		$this->renameColumn('ophcotherapya_exceptional_previntervention', 'start_date', 'treatment_date');
		$this->dropColumn('ophcotherapya_exceptional_previntervention', 'end_va');
		$this->dropColumn('ophcotherapya_exceptional_previntervention', 'start_va');
		$this->alterColumn('ophcotherapya_patientsuit_decisiontreenoderesponse','value','varchar(16)');
		$this->dropColumn('et_ophcotherapya_exceptional', 'right_patient_expectations');
		$this->dropColumn('et_ophcotherapya_exceptional', 'left_patient_expectations');
		$this->dropColumn('ophcotherapya_filecoll', 'summary');
	}

	/*
	// Use safeUp/safeDown to do migration with transaction
	public function safeUp()
	{
	}

	public function safeDown()
	{
	}
	*/
}