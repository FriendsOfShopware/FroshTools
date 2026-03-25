const defaultSearchConfiguration = {
    _searchable: true,
    name: {
        _searchable: true,
        _score: 500,
    },
    eventName: {
        _searchable: true,
        _score: 250,
    },
    url: {
        _searchable: true,
        _score: 80,
    },
};

export default defaultSearchConfiguration;
