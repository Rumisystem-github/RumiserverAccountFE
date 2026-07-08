let dialog = new DIALOG_SYSTEM();
let session = null;
let self_user = null;

window.addEventListener("load", async (e)=>{
	session = ReadCOOKIE().SESSION;
	if (session == null) {
		window.location.href = "/Login";
		return;
	}

	self_user = await LOGIN(session);
	if (self_user == false) {
		window.location.href = "/Login";
		return;
	}

	if (window["__ready"] != null) {
		window["__ready"]();
	}
});

function copy(text) {
	if (navigator.clipboard == null) {
		return;
	}

	navigator.clipboard.writeText(text);
}