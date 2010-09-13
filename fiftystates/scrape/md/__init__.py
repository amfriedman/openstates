import datetime

metadata = dict(
    name='Maryland',
    abbreviation='md',
    legislature_name='Maryland General Assembly',
    upper_chamber_name='Senate',
    lower_chamber_name='House of Delegates',
    upper_chamber_title='Senator',
    lower_chamber_title='Delegate',
    upper_chamber_term=4,
    lower_chamber_term=4,
    terms=[
        {'name': '2007-2010', 'sessions': ['2007', '2007s1', '2008',
                                           '2009', '2010'],
         'start_year': 2007, 'end_year': 2010},
    ],
    session_details={
        '2007': {'start_date': datetime.date(2007,1,10),
                 'end_date': datetime.date(2007,4,10),
                 'number': 423,
                 'type': 'primary'},
        '2007s1': {'start_date': datetime.date(2007,10,29),
                   'end_date': datetime.date(2007,11,19),
                   'number': 424,
                   'type': 'special'},
        '2008': {'start_date': datetime.date(2008,1,9),
                 'end_date': datetime.date(2008,4,7),
                 'number': 425,
                 'type': 'primary'},
        '2009': {'start_date': datetime.date(2009,1,14),
                 'end_date': datetime.date(2009,4,13),
                 'number': 426,
                 'type': 'primary'},
        '2010': {'start_date': datetime.date(2010,1,13),
                 'end_date': datetime.date(2010,4,12),
                 'number': 427,
                 'type': 'primary'},
    },
)
