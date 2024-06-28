document.addEventListener('DOMContentLoaded', function() {
	const accordionId = document.location.hash.substring(1);
	const accordion = document.getElementById('panel-in-session-by-' + accordionId);
	if (accordion) {
		accordion.classList.add('show');
		accordion.scrollIntoView({behavior: "smooth"});
	}
});