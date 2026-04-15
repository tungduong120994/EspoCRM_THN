<?php
return (object) [
  'scopes' => (object) [
    'Currency' => (object) [
      'read' => 'no',
      'edit' => 'no'
    ],
    'Team' => (object) [
      'read' => 'all'
    ],
    'Template' => (object) [
      'read' => 'all',
      'stream' => 'all',
      'edit' => 'all',
      'delete' => 'no',
      'create' => 'yes'
    ],
    'User' => (object) [
      'read' => 'all',
      'edit' => 'no'
    ],
    'Account' => (object) [
      'read' => 'all',
      'stream' => 'no',
      'edit' => 'all',
      'delete' => 'no',
      'create' => 'yes'
    ],
    'Lead' => (object) [
      'read' => 'all',
      'stream' => 'no',
      'edit' => 'all',
      'delete' => 'no',
      'create' => 'yes'
    ],
    'COrder' => (object) [
      'read' => 'all',
      'stream' => 'no',
      'edit' => 'all',
      'delete' => 'all',
      'create' => 'yes'
    ],
    'COrderItem' => (object) [
      'read' => 'all',
      'stream' => 'all',
      'edit' => 'all',
      'delete' => 'all',
      'create' => 'yes'
    ],
    'CParcel' => (object) [
      'read' => 'all',
      'stream' => 'all',
      'edit' => 'all',
      'delete' => 'all',
      'create' => 'yes'
    ],
    'CProduct' => (object) [
      'read' => 'all',
      'stream' => 'no',
      'edit' => 'all',
      'delete' => 'all',
      'create' => 'yes'
    ],
    'CShipment' => (object) [
      'read' => 'all',
      'stream' => 'all',
      'edit' => 'all',
      'delete' => 'all',
      'create' => 'yes'
    ],
    'Import' => false,
    'Webhook' => false,
    'Email' => false,
    'EmailAccountScope' => false,
    'EmailTemplate' => false,
    'EmailTemplateCategory' => false,
    'ExternalAccount' => false,
    'GlobalStream' => false,
    'WorkingTimeCalendar' => false,
    'Activities' => false,
    'Calendar' => false,
    'Call' => false,
    'Campaign' => false,
    'Case' => false,
    'Contact' => false,
    'Document' => false,
    'DocumentFolder' => false,
    'KnowledgeBaseArticle' => false,
    'KnowledgeBaseCategory' => false,
    'Meeting' => false,
    'Opportunity' => false,
    'TargetList' => false,
    'TargetListCategory' => false,
    'Task' => false,
    'Target' => false,
    'Note' => (object) [
      'read' => 'own',
      'edit' => 'own',
      'delete' => 'own',
      'create' => 'yes'
    ],
    'Portal' => (object) [
      'read' => 'all',
      'edit' => 'no',
      'delete' => 'no',
      'create' => 'no'
    ],
    'Attachment' => (object) [
      'read' => 'own',
      'edit' => 'own',
      'delete' => 'own',
      'create' => 'yes'
    ],
    'EmailAccount' => (object) [
      'read' => 'own',
      'edit' => 'own',
      'delete' => 'own',
      'create' => 'yes'
    ],
    'EmailFilter' => (object) [
      'read' => 'own',
      'edit' => 'own',
      'delete' => 'own',
      'create' => 'yes'
    ],
    'EmailFolder' => (object) [
      'read' => 'own',
      'edit' => 'own',
      'delete' => 'own',
      'create' => 'yes'
    ],
    'GroupEmailFolder' => (object) [
      'read' => 'team',
      'edit' => 'no',
      'delete' => 'no',
      'create' => 'no'
    ],
    'Preferences' => (object) [
      'read' => 'own',
      'edit' => 'own',
      'delete' => 'no',
      'create' => 'no'
    ],
    'Notification' => (object) [
      'read' => 'own',
      'edit' => 'no',
      'delete' => 'own',
      'create' => 'no'
    ],
    'ActionHistoryRecord' => (object) [
      'read' => 'own'
    ],
    'Role' => false,
    'PortalRole' => false,
    'ImportError' => false,
    'ImportEml' => false,
    'WorkingTimeRange' => false,
    'Stream' => true,
    'MassEmail' => false,
    'CampaignLogRecord' => false,
    'CampaignTrackingUrl' => false,
    'EmailQueueItem' => false
  ],
  'fields' => (object) [
    'Email' => (object) [],
    'Team' => (object) [],
    'User' => (object) [
      'gender' => (object) [
        'read' => 'no',
        'edit' => 'no'
      ],
      'avatarColor' => (object) [
        'read' => 'yes',
        'edit' => 'no'
      ],
      'dashboardTemplate' => (object) [
        'read' => 'no',
        'edit' => 'no'
      ],
      'workingTimeCalendar' => (object) [
        'read' => 'yes',
        'edit' => 'no'
      ],
      'password' => (object) [
        'read' => 'no',
        'edit' => 'no'
      ],
      'passwordConfirm' => (object) [
        'read' => 'no',
        'edit' => 'no'
      ],
      'auth2FA' => (object) [
        'read' => 'no',
        'edit' => 'no'
      ],
      'authMethod' => (object) [
        'read' => 'no',
        'edit' => 'no'
      ],
      'apiKey' => (object) [
        'read' => 'no',
        'edit' => 'no'
      ],
      'secretKey' => (object) [
        'read' => 'no',
        'edit' => 'no'
      ],
      'token' => (object) [
        'read' => 'no',
        'edit' => 'no'
      ]
    ],
    'Account' => (object) [],
    'Call' => (object) [
      'uid' => (object) [
        'read' => 'no',
        'edit' => 'no'
      ]
    ],
    'Campaign' => (object) [],
    'Case' => (object) [],
    'Contact' => (object) [],
    'Document' => (object) [],
    'DocumentFolder' => (object) [],
    'KnowledgeBaseArticle' => (object) [],
    'KnowledgeBaseCategory' => (object) [],
    'Lead' => (object) [],
    'Meeting' => (object) [
      'uid' => (object) [
        'read' => 'no',
        'edit' => 'no'
      ]
    ],
    'Opportunity' => (object) [],
    'TargetList' => (object) [],
    'TargetListCategory' => (object) [],
    'Task' => (object) [],
    'COrder' => (object) [],
    'COrderItem' => (object) [],
    'CParcel' => (object) [],
    'CProduct' => (object) [],
    'CShipment' => (object) [],
    'ActionHistoryRecord' => (object) [
      'authToken' => (object) [
        'read' => 'no',
        'edit' => 'no'
      ],
      'authLogRecord' => (object) [
        'read' => 'no',
        'edit' => 'no'
      ]
    ],
    'EmailAccount' => (object) [
      'assignedUser' => (object) [
        'read' => 'yes',
        'edit' => 'no'
      ]
    ],
    'EmailFolder' => (object) [
      'assignedUser' => (object) [
        'read' => 'yes',
        'edit' => 'no'
      ]
    ]
  ],
  'permissions' => (object) [
    'assignment' => 'all',
    'message' => 'no',
    'mention' => 'all',
    'userCalendar' => 'no',
    'audit' => 'no',
    'export' => 'yes',
    'massUpdate' => 'no',
    'user' => 'all',
    'portal' => 'no',
    'groupEmailAccount' => 'no',
    'followerManagement' => 'no',
    'dataPrivacy' => 'no'
  ]
];
