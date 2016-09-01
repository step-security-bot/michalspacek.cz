$(document).ready(function() {
	$('#pridat-ucastniky a[href="#pridat-ucastniky"]').click(function() {
		$('#pridat-ucastniky-container').slideToggle('fast');
		return false;
	});

	var Applications = {};
	Applications.reindex = function(element, index) {
		element.find('input').each(function() {
			$(this).attr('name', function(i, value) {
				return value.replace(/^applications\[\d+\]/, 'applications[' + index + ']');
			});
			$(this).attr('id', function(i, value) {
				return value.replace(/^frm-applications-applications-\d+-/, 'frm-applications-applications-' + index + '-');
			});
			if ($(this).attr('data-nette-rules') !== undefined) {
				$(this).attr('data-nette-rules', $(this).attr('data-nette-rules').replace(/"applications\[\d+\]/, '"applications[' + index + ']'));
			}
		});
	};
	$('#pridat-ucastniky .add').click(function() {
		$('#pridat-ucastniky .delete').show();
		var index = 0;
		tr = $(this).parent().parent();
		tr.after(tr.clone(true));
		tr.parent().children('tr').each(function() {
			Applications.reindex($(this), index++);
		});
	});
	$('#pridat-ucastniky .delete').click(function() {
		var index = 0;
		tr = $(this).parent().parent();
		tr.addClass('highlight');
		if (confirm('Odebrat účastníka?')) {
			tbody = tr.parent();
			tr.remove();
			tbody.children('tr').each(function() {
				Applications.reindex($(this), index++);
			});
			if (tbody.children('tr').length == 1) {
				$('#pridat-ucastniky .delete').hide();
			}
		} else {
			tr.removeClass('highlight');
		}
	});

	$('#pridat-soubor a[href="#pridat-soubor"]').click(function() {
		$('#pridat-soubor-container').slideToggle('fast');
		return false;
	});

	$('#upravit-termin a[href="#upravit-termin"]').click(function() {
		$('#upravit-termin-container').slideToggle('fast');
		return false;
	});

	$('#pridat-termin a[href="#pridat-termin"]').click(function() {
		$('#pridat-termin-container').slideToggle('fast');
		return false;
	});

	$('#pridat-prednasku a[href="#pridat-prednasku"]').click(function() {
		$('#pridat-prednasku-container').slideToggle('fast');
		return false;
	});

	$('#pridat-rozhovor a[href="#pridat-rozhovor"]').click(function() {
		$('#pridat-rozhovor-container').slideToggle('fast');
		return false;
	});

	$('#pridat-storage a[href="#pridat-storage"]').click(function() {
		$('#pridat-storage-container').slideToggle('fast');
		return false;
	});

	$('#statuses td[data-date]')
		.click(function() {
			$('#date-' + $(this).data('date')).toggle();
			return false;
		})
		.css('cursor', 'pointer');

	$('#statusesShow').click(function() {
		$('#statuses td[data-date]').parent().next().show();
		$('#statuses-links').find('span').toggle();
	});

	$('#statusesHide').click(function() {
		$('#statuses td[data-date]').parent().next().hide();
		$('#statuses-links').find('span').toggle();
	});

	$('.preset').click(function(event) {
		event.preventDefault();
		var preset = $(this).data('preset');
		$('#frm-statuses-date').val($(this).data('start'));
		$('#applications .status option').each(function() {
			if (this.value == preset) {
				$(this).parent().val(preset);
				return;
			}
		});
	});

	$('#emails tbody .button').click(function() {
		$(this).closest('tr').nextUntil('.row', '.expand-container').toggle();
	});

	$('#emails #checkAll').click(function(event) {
		event.preventDefault();
		$('#emails .row .send').prop('checked', true).attr('checked', true);
	});

	$('#emails #uncheckAll').click(function(event) {
		event.preventDefault();
		$('#emails .row .send').prop('checked', false).attr('checked', false);
	});

	$('#statusHistory-link').click(function(event) {
		event.preventDefault();
		$('#statusHistory-container').slideToggle('fast');
	});

	$('a[href*="#new"]').click(function(event) {
		event.preventDefault();
		$($(this).attr('href')).toggle();
	});
});
