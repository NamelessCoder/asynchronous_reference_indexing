#
# Table structure for table 'tx_asyncreferenceindexing_queue'
#
CREATE TABLE tx_asyncreferenceindexing_queue (
  reference_table char(64) DEFAULT '' NOT NULL,
  reference_uid int(11) DEFAULT '0' NOT NULL,
  reference_workspace int(11) DEFAULT '0' NOT NULL,

  INDEX reference_table (reference_table),
  INDEX reference_uid (reference_uid),
  INDEX reference_workspace (reference_workspace)
);
